<?php

namespace App\Core;

use App\Services\AuditLogService;

trait AuditsChanges
	{
	/**
	 * Returns an audit service bound to the current write connection.
	 * @return AuditLogService
	 **/
	protected function auditLogger(): AuditLogService
		{
		return new AuditLogService($this->db);
		}

	/**
	 * Writes a create event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context.
	 **/
	protected function auditCreate(string $module, string $entityName, $entityId, $afterData, array $context = []): void
		{
		$this->auditLogger()->logCreate($module, $entityName, $entityId, $afterData, $context);
		}

	/**
	 * Writes an update event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the change.
	 * @param mixed $afterData Snapshot after the change.
	 * @param array $context Additional context.
	 **/
	protected function auditUpdate(string $module, string $entityName, $entityId, $beforeData, $afterData, array $context = []): void
		{
		$this->auditLogger()->logUpdate($module, $entityName, $entityId, $beforeData, $afterData, $context);
		}

	/**
	 * Writes a delete event.
	 * @param string $module Source module name.
	 * @param string $entityName Main entity/table name.
	 * @param mixed $entityId Main entity identifier.
	 * @param mixed $beforeData Snapshot before the delete.
	 * @param array $context Additional context.
	 **/
	protected function auditDelete(string $module, string $entityName, $entityId, $beforeData, array $context = []): void
		{
		$this->auditLogger()->logDelete($module, $entityName, $entityId, $beforeData, $context);
		}
	}
?>
