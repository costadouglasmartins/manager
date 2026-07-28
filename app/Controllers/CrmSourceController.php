<?php

namespace App\Controllers;

use App\Core\CrmCatalogController;

class CrmSourceController extends CrmCatalogController
	{
	public function index(): void
		{
		$this->catalogIndex('source', 'crm_source.index');
		}

	public function create(): void
		{
		$this->catalogCreate('source', 'crm_source.create');
		}

	public function store(): void
		{
		$this->catalogStore('source', 'crm_source.store');
		}

	public function edit($id): void
		{
		$this->catalogEdit('source', 'crm_source.edit', $id);
		}

	public function update($id): void
		{
		$this->catalogUpdate('source', 'crm_source.update', $id);
		}

	public function informations($id): void
		{
		$this->catalogInformations('source', 'crm_source.informations', $id);
		}

	public function delete($id): void
		{
		$this->catalogDelete('source', 'crm_source.delete', $id);
		}
	}
?>
