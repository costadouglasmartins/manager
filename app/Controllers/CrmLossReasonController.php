<?php

namespace App\Controllers;

use App\Core\CrmCatalogController;

class CrmLossReasonController extends CrmCatalogController
	{
	public function index(): void
		{
		$this->catalogIndex('loss_reason', 'crm_loss_reason.index');
		}

	public function create(): void
		{
		$this->catalogCreate('loss_reason', 'crm_loss_reason.create');
		}

	public function store(): void
		{
		$this->catalogStore('loss_reason', 'crm_loss_reason.store');
		}

	public function edit($id): void
		{
		$this->catalogEdit('loss_reason', 'crm_loss_reason.edit', $id);
		}

	public function update($id): void
		{
		$this->catalogUpdate('loss_reason', 'crm_loss_reason.update', $id);
		}

	public function informations($id): void
		{
		$this->catalogInformations('loss_reason', 'crm_loss_reason.informations', $id);
		}

	public function delete($id): void
		{
		$this->catalogDelete('loss_reason', 'crm_loss_reason.delete', $id);
		}
	}
?>
