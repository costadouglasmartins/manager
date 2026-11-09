<?php

namespace App\Controllers;

use App\Core\HrCatalogController;
use App\Models\HrPosition;

/**
 * Tenant HR catalog of positions. The flow lives in HrCatalogController; each action checks its own ability first.
 **/
class HrPositionController extends HrCatalogController
	{
	/**
	 * Lists records with filters and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('hr_position.index');
		$this->catalogIndex(new HrPosition());
		}

	/**
	 * Downloads every record matching the current filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads every record matching the current filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the creation form.
	 **/
	public function create(): void
		{
		$this->requirePermission('hr_position.create');
		$this->catalogCreate(new HrPosition());
		}

	/**
	 * Persists a new record (POST only).
	 **/
	public function store(): void
		{
		$this->requirePermission('hr_position.store');
		$this->catalogStore(new HrPosition());
		}

	/**
	 * Renders the edit form.
	 * @param mixed $id Record identifier from the route.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('hr_position.edit');
		$this->catalogEdit(new HrPosition(), $id);
		}

	/**
	 * Updates a record (POST only).
	 * @param mixed $id Record identifier from the route.
	 **/
	public function update($id): void
		{
		$this->requirePermission('hr_position.update');
		$this->catalogUpdate(new HrPosition(), $id);
		}

	/**
	 * Displays record details.
	 * @param mixed $id Record identifier from the route.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('hr_position.informations');
		$this->catalogInformations(new HrPosition(), $id);
		}

	/**
	 * Deletes a record unless employees still use it (POST only).
	 * @param mixed $id Record identifier from the route.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('hr_position.delete');
		$this->catalogDelete(new HrPosition(), $id);
		}
	}
?>
