<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class ProductUnit extends TenantModel
	{
	public function getOptions(?string $measurementType = null, bool $activeOnly = true): array
		{
		$sql = 'SELECT id, code, name, symbol, measurement_type, factor_to_base, decimal_places, active FROM product_units WHERE 1 = 1';
		$params = [];
		if ($measurementType !== null && trim($measurementType) !== '')
			{
			$sql .= ' AND measurement_type = :measurement_type';
			$params['measurement_type'] = strtoupper(trim($measurementType));
			}
		if ($activeOnly) $sql .= ' AND active = 1';
		$sql .= ' ORDER BY measurement_type, factor_to_base, name';
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function groupedOptions(): array
		{
		$groups = [];
		foreach ($this->getOptions() as $unit) $groups[(string) $unit['measurement_type']][] = $unit;
		return $groups;
		}
	}
