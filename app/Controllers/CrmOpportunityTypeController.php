<?php

namespace App\Controllers;

use App\Core\CrmCatalogController;

class CrmOpportunityTypeController extends CrmCatalogController
	{
	public function index(): void
		{
		$this->catalogIndex('opportunity_type', 'crm_opportunity_type.index');
		}

	public function create(): void
		{
		$this->catalogCreate('opportunity_type', 'crm_opportunity_type.create');
		}

	public function store(): void
		{
		$this->catalogStore('opportunity_type', 'crm_opportunity_type.store');
		}

	public function edit($id): void
		{
		$this->catalogEdit('opportunity_type', 'crm_opportunity_type.edit', $id);
		}

	public function update($id): void
		{
		$this->catalogUpdate('opportunity_type', 'crm_opportunity_type.update', $id);
		}

	public function informations($id): void
		{
		$this->catalogInformations('opportunity_type', 'crm_opportunity_type.informations', $id);
		}

	public function delete($id): void
		{
		$this->catalogDelete('opportunity_type', 'crm_opportunity_type.delete', $id);
		}
	}
?>
