<?php

namespace App\Models;

/**
 * Tenant catalog of HR positions (table hr_positions).
 **/
class HrPosition extends HrCatalog
	{
	/**
	 * Returns the catalog table name.
	 * @return string Table name.
	 **/
	protected function tableName(): string
		{
		return 'hr_positions';
		}

	/**
	 * Returns the hr_employees column that references positions.
	 * @return string Column name.
	 **/
	protected function employeeColumn(): string
		{
		return 'hr_position_id';
		}

	/**
	 * Returns the audit module name, which is also the route resource.
	 * @return string Module name.
	 **/
	protected function auditModule(): string
		{
		return 'hr_position';
		}

	/**
	 * Returns the labels and routes shared with the views.
	 * @return array<string, string> Catalog metadata.
	 **/
	public function meta(): array
		{
		return [
			'key'                   => 'position',
			'resource'              => 'hr_position',
			'icon'                  => 'bi-person-badge',
			'label'                 => 'cargo',
			'empty_message'         => 'Nenhum cargo encontrado.',
			'singular'              => 'Cargo',
			'plural'                => 'Cargos',
			'list_title'            => 'Cargos',
			'create_title'          => 'Novo cargo',
			'edit_title'            => 'Editar cargo',
			'information_title'     => 'Informações do cargo',
			'delete_description'    => 'o cargo',
			'not_found_message'     => 'Cargo não encontrado.',
			'name_required_message' => 'Informe o nome do cargo.',
			'duplicate_message'     => 'Já existe um cargo com este nome.',
			'in_use_message'        => 'Este cargo está em uso por %d funcionário(s). Inative-o em vez de excluir.',
			'saved_message'         => 'Cargo salvo com sucesso.',
			'updated_message'       => 'Cargo atualizado com sucesso.',
			'deleted_message'       => 'Cargo excluído com sucesso.',
		];
		}
	}
?>
