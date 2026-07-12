<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\Licence\Kit\Enums\AuditEventType;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Enums\TransferStatus;
use Simtabi\Laranail\Licence\Kit\Enums\TransferType;
use Simtabi\Laranail\Licence\Kit\Enums\UsageStatus;
use Simtabi\Laranail\Licence\Kit\Events\LicenseTransferCompleted;
use Simtabi\Laranail\Licence\Kit\Events\LicenseTransferInitiated;
use Simtabi\Laranail\Licence\Kit\Events\LicenseTransferRejected;
use Simtabi\Laranail\Licence\Kit\Exceptions\TransferNotAllowedException;
use Simtabi\Laranail\Licence\Kit\Models\License;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTransfer;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTransferApproval;
use Simtabi\Laranail\Licence\Kit\Models\LicenseTransferHistory;

class LicenseTransferService
{
    public function __construct(
        protected TransferValidationService $validationService,
        protected TransferApprovalService $approvalService,
        protected AuditLoggerService $auditLogger
    ) {}

    public function initiateTransfer(
        License $license,
        Model $targetEntity,
        TransferType $transferType,
        Model $initiator,
        array $options = []
    ): LicenseTransfer {
        return DB::transaction(function () use ($license, $targetEntity, $transferType, $initiator, $options): LicenseTransfer {
            $this->validationService->validateTransferEligibility($license, $targetEntity, $transferType);

            $transfer = new LicenseTransfer([
                'license_id' => $license->id,
                'from_licensable_type' => $license->licensable_type,
                'from_licensable_id' => $license->licensable_id,
                'to_licensable_type' => $targetEntity::class,
                'to_licensable_id' => $targetEntity->getKey(),
                'transfer_type' => $transferType,
                'initiated_by_type' => $initiator::class,
                'initiated_by_id' => $initiator->getKey(),
                'reason' => $options['reason'] ?? null,
                'preserve_usages' => $options['preserve_usages'] ?? $transferType->canPreserveUsages(),
                'preserve_expiration' => $options['preserve_expiration'] ?? true,
                'reset_activation' => $options['reset_activation'] ?? false,
                'conditions' => $options['conditions'] ?? null,
                'metadata' => $options['metadata'] ?? null,
                'expires_at' => now()->addDays(7),
            ]);

            $transfer->save();

            $this->createRequiredApprovals($transfer);

            event(new LicenseTransferInitiated($transfer));

            $this->auditLogger->log(AuditEventType::TransferInitiated, [
                'transfer_id' => $transfer->id,
                'license_id' => $license->id,
                'from' => $license->licensable_type.':'.$license->licensable_id,
                'to' => $transfer->to_licensable_type.':'.$transfer->to_licensable_id,
                'initiator' => $initiator::class.':'.$initiator->getKey(),
            ]);

            return $transfer;
        });
    }

    public function approveTransfer(
        LicenseTransferApproval $approval,
        Model $approver,
        ?string $reason = null
    ): void {
        if (! $this->approvalService->canApprove($approval, $approver)) {
            throw new TransferNotAllowedException('You are not authorized to approve this transfer');
        }

        DB::transaction(function () use ($approval, $approver, $reason): void {
            $approval->approve($approver, $reason);

            /** @var LicenseTransfer $transfer */
            $transfer = $approval->transfer;

            if ($transfer->canBeExecuted()) {
                $this->executeTransfer($transfer);
            }

            $this->auditLogger->log(AuditEventType::TransferApproved, [
                'transfer_id' => $transfer->id,
                'approval_type' => $approval->approval_type,
                'approver' => $approver::class.':'.$approver->getKey(),
            ]);
        });
    }

    public function rejectTransfer(
        LicenseTransferApproval $approval,
        Model $rejector,
        string $reason
    ): void {
        if (! $this->approvalService->canReject($approval, $rejector)) {
            throw new TransferNotAllowedException('You are not authorized to reject this transfer');
        }

        DB::transaction(function () use ($approval, $rejector, $reason): void {
            $approval->reject($rejector, $reason);

            /** @var LicenseTransfer $transfer */
            $transfer = $approval->transfer;

            event(new LicenseTransferRejected($transfer));

            $this->auditLogger->log(AuditEventType::TransferRejected, [
                'transfer_id' => $transfer->id,
                'rejector' => $rejector::class.':'.$rejector->getKey(),
                'reason' => $reason,
            ]);
        });
    }

    protected function executeTransfer(LicenseTransfer $transfer): void
    {
        /** @var License $license */
        $license = $transfer->license;

        $previousSnapshot = $this->createSnapshot($license);

        $this->handleUsages($transfer, $license);

        $license->update([
            'licensable_type' => $transfer->to_licensable_type,
            'licensable_id' => $transfer->to_licensable_id,
        ]);

        if ($transfer->reset_activation) {
            $license->update([
                'activated_at' => null,
                'status' => LicenseStatus::Pending,
            ]);
        }

        if (! $transfer->preserve_expiration && isset($transfer->conditions['new_expiration'])) {
            $license->update([
                'expires_at' => $transfer->conditions['new_expiration'],
            ]);
        }

        /** @var License $freshLicense */
        $freshLicense = $license->fresh();
        $newSnapshot = $this->createSnapshot($freshLicense);

        $this->createTransferHistory($transfer, $previousSnapshot, $newSnapshot);

        /** @var Model $initiator */
        $initiator = $transfer->initiatedBy;
        $transfer->markAsCompleted($initiator);

        event(new LicenseTransferCompleted($transfer));

        $this->auditLogger->log(AuditEventType::TransferCompleted, [
            'transfer_id' => $transfer->id,
            'license_id' => $license->id,
        ]);
    }

    protected function handleUsages(LicenseTransfer $transfer, License $license): void
    {
        if ($transfer->preserve_usages) {
            return;
        }

        $revokedCount = $license->activeUsages()
            ->update([
                'status' => UsageStatus::Revoked,
                'revoked_at' => now(),
            ]);

        $transfer->update([
            'metadata' => array_merge($transfer->metadata ?? [], [
                'usages_revoked_count' => $revokedCount,
            ]),
        ]);
    }

    protected function createSnapshot(License $license): array
    {
        return [
            'license_id' => $license->id,
            'licensable_type' => $license->licensable_type,
            'licensable_id' => $license->licensable_id,
            'status' => $license->status->value,
            'activated_at' => $license->activated_at?->toISOString(),
            'expires_at' => $license->expires_at?->toISOString(),
            'max_usages' => $license->max_usages,
            'active_usages_count' => $license->activeUsages()->count(),
            'meta' => $license->meta?->toArray(),
        ];
    }

    protected function createTransferHistory(
        LicenseTransfer $transfer,
        array $previousSnapshot,
        array $newSnapshot
    ): void {
        LicenseTransferHistory::create([
            'license_id' => $transfer->license_id,
            'transfer_id' => $transfer->id,
            'previous_licensable_type' => $previousSnapshot['licensable_type'],
            'previous_licensable_id' => $previousSnapshot['licensable_id'],
            'new_licensable_type' => $newSnapshot['licensable_type'],
            'new_licensable_id' => $newSnapshot['licensable_id'],
            'previous_snapshot' => $previousSnapshot,
            'new_snapshot' => $newSnapshot,
            'transfer_type' => $transfer->transfer_type->value,
            'executed_by_type' => $transfer->initiated_by_type,
            'executed_by_id' => $transfer->initiated_by_id,
            'usages_preserved' => $transfer->preserve_usages,
            'expiration_preserved' => $transfer->preserve_expiration,
            'activation_reset' => $transfer->reset_activation,
            'usages_transferred_count' => $transfer->preserve_usages ? $previousSnapshot['active_usages_count'] : 0,
            'usages_revoked_count' => $transfer->preserve_usages ? 0 : $previousSnapshot['active_usages_count'],
        ]);
    }

    protected function createRequiredApprovals(LicenseTransfer $transfer): void
    {
        $approvalTypes = $this->approvalService->determineRequiredApprovals($transfer);

        foreach ($approvalTypes as $type => $config) {
            if (! $config['required']) {
                continue;
            }

            LicenseTransferApproval::create([
                'transfer_id' => $transfer->id,
                'approval_type' => $type,
                'approver_type' => $config['approver_type'] ?? null,
                'approver_id' => $config['approver_id'] ?? null,
                'token_expires_at' => now()->addHours($config['timeout_hours'] ?? 72),
            ]);
        }
    }

    public function cancelTransfer(LicenseTransfer $transfer, Model $canceller): void
    {
        if ($transfer->status !== TransferStatus::Pending) {
            throw new TransferNotAllowedException('Only pending transfers can be cancelled');
        }

        if (! $this->canCancelTransfer($transfer, $canceller)) {
            throw new TransferNotAllowedException('You are not authorized to cancel this transfer');
        }

        $transfer->markAsCancelled();

        $this->auditLogger->log(AuditEventType::TransferCancelled, [
            'transfer_id' => $transfer->id,
            'cancelled_by' => $canceller::class.':'.$canceller->getKey(),
        ]);
    }

    protected function canCancelTransfer(LicenseTransfer $transfer, Model $user): bool
    {
        if ($transfer->initiated_by_type === $user::class &&
            $transfer->initiated_by_id === $user->getKey()) {
            return true;
        }

        return $transfer->from_licensable_type === $user::class &&
            $transfer->from_licensable_id === $user->getKey();
    }

    public function expireStaleTransfers(): int
    {
        $expired = LicenseTransfer::pending()
            ->where('expires_at', '<', now())
            ->update(['status' => TransferStatus::Expired]);

        if ($expired > 0) {
            $this->auditLogger->log(AuditEventType::TransferExpired, [
                'count' => $expired,
            ]);
        }

        return $expired;
    }
}
