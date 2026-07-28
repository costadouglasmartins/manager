<?php

namespace App\Controllers;

use App\Core\CrmCatalogController;

class CrmInactivityReasonController extends CrmCatalogController
	{
	public function index(): void
		{
		$this->catalogIndex('inactivity_reason', 'crm_inactivity_reason.index');
		}

	public function create(): void
		{
		$this->catalogCreate('inactivity_reason', 'crm_inactivity_reason.create');
		}

	public function store(): void
		{
		$this->catalogStore('inactivity_reason', 'crm_inactivity_reason.store');
		}

	public function edit($id): void
		{
		$this->catalogEdit('inactivity_reason', 'crm_inactivity_reason.edit', $id);
		}

	public function update($id): void
		{
		$this->catalogUpdate('inactivity_reason', 'crm_inactivity_reason.update', $id);
		}

	public function informations($id): void
		{
		$this->catalogInformations('inactivity_reason', 'crm_inactivity_reason.informations', $id);
		}

	public function delete($id): void
		{
		$this->catalogDelete('inactivity_reason', 'crm_inactivity_reason.delete', $id);
		}
	}
?>
