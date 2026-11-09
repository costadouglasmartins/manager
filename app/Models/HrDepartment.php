<?php

namespace App\Models;

/**
 * Tenant catalog of HR departments (table hr_departments).
 **/
class HrDepartment extends HrCatalog
	{
	/**
	 * Returns the catalog table name.
	 * @return string Table name.
	 **/
	protected function tableName(): string
		{
		return 'hr_departments';
		}

	/**
	 * Returns the hr_employees column that references departments.
	 * @return string Column name.
	 **/
	protected function employeeColumn(): string
		{
		return 'hr_department_id';
		}

	/**
	 * Returns the audit module name, which is also the route resource.
	 * @return string Module name.
	 **/
	protected function auditModule(): string
		{
		return 'hr_department';
		}

	/**
	 * Returns the labels and routes shared with the views.
	 * @return array<string, string> Catalog metadata.
	 **/
	public function meta(): array
		{
		return [
			'key'                   => 'department',
			'resource'              => 'hr_department',
			'icon'                  => 'bi-diagram-3',
			'label'                 => 'departamento',
			'empty_message'         => 'Nenhum departamento encontrado.',
			'singular'              => 'Departamento',
			'plural'                => 'Departamentos',
			'list_title'            => 'Departamentos',
			'create_title'          => 'Novo departamento',
			'edit_title'            => 'Editar departamento',
			'information_title'     => 'Informações do departamento',
			'delete_description'    => 'o departamento',
			'not_found_message'     => 'Departamento não encontrado.',
			'name_required_message' => 'Informe o nome do departamento.',
			'duplicate_message'     => 'Já existe um departamento com este nome.',
			'in_use_message'        => 'Este departamento está em uso por %d funcionário(s). Inative-o em vez de excluir.',
			'saved_message'         => 'Departamento salvo com sucesso.',
			'updated_message'       => 'Departamento atualizado com sucesso.',
			'deleted_message'       => 'Departamento excluído com sucesso.',
		];
		}
	}
?>
