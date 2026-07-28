<?php

namespace App\Controllers;

use App\Core\CrmCatalogController;

class CrmPipelineStageController extends CrmCatalogController
	{
	public function index(): void
		{
		$this->catalogIndex('pipeline_stage', 'crm_pipeline_stage.index');
		}

	public function create(): void
		{
		$this->catalogCreate('pipeline_stage', 'crm_pipeline_stage.create');
		}

	public function store(): void
		{
		$this->catalogStore('pipeline_stage', 'crm_pipeline_stage.store');
		}

	public function edit($id): void
		{
		$this->catalogEdit('pipeline_stage', 'crm_pipeline_stage.edit', $id);
		}

	public function update($id): void
		{
		$this->catalogUpdate('pipeline_stage', 'crm_pipeline_stage.update', $id);
		}

	public function informations($id): void
		{
		$this->catalogInformations('pipeline_stage', 'crm_pipeline_stage.informations', $id);
		}

	public function delete($id): void
		{
		$this->catalogDelete('pipeline_stage', 'crm_pipeline_stage.delete', $id);
		}
	}
?>
