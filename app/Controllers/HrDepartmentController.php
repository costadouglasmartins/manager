<?php

namespace App\Controllers;

use App\Core\HrCatalogController;
use App\Models\HrDepartment;

/**
 * Tenant HR catalog of departments. The flow lives in HrCatalogController; each action checks its own ability first.
 **/
class HrDepartmentController extends HrCatalogController
	{
	/**
	 * Lists records with filters and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('hr_department.index');
		$this->catalogIndex(new HrDepartment());
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
		$this->requirePermission('hr_department.create');
		$this->catalogCreate(new HrDepartment());
		}

	/**
	 * Persists a new record (POST only).
	 **/
	public function store(): void
		{
		$this->requirePermission('hr_department.store');
		$this->catalogStore(new HrDepartment());
		}

	/**
	 * Renders the edit form.
	 * @param mixed $id Record identifier from the route.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('hr_department.edit');
		$this->catalogEdit(new HrDepartment(), $id);
		}

	/**
	 * Updates a record (POST only).
	 * @param mixed $id Record identifier from the route.
	 **/
	public function update($id): void
		{
		$this->requirePermission('hr_department.update');
		$this->catalogUpdate(new HrDepartment(), $id);
		}

	/**
	 * Displays record details.
	 * @param mixed $id Record identifier from the route.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('hr_department.informations');
		$this->catalogInformations(new HrDepartment(), $id);
		}

	/**
	 * Deletes a record unless employees still use it (POST only).
	 * @param mixed $id Record identifier from the route.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('hr_department.delete');
		$this->catalogDelete(new HrDepartment(), $id);
		}
	}
?>
