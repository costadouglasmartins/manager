<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class TenantPermission extends Model
  {
    /**
     * Returns the abilities assigned to a user inside a specific tenant.
     */
    public function getPermissionsByTenantUser(int $tenantId, int $userId): array
      {
      $stmt = $this->db->prepare("
        SELECT tp.ability
        FROM tenant_user_permissions tup
        INNER JOIN tenant_permissions tp ON tp.id = tup.permission_id
        WHERE tup.tenant_id = :tenant_id
          AND tup.user_id = :user_id
        ORDER BY tp.ability ASC
      ");
      $stmt->execute([
        'tenant_id' => $tenantId,
        'user_id' => $userId,
      ]);

      $abilities = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
        $abilities[] = (string) $row['ability'];
        }

      return $abilities;
      }

    /**
     * Replaces the tenant-scoped permissions assigned to a user.
     */
    public function setPermissionsForTenantUser(int $tenantId, int $userId, array $permissionIds): void
      {
      $now = date('Y-m-d H:i:s');
      $this->db->beginTransaction();

      try
        {
        $delete = $this->db->prepare("
          DELETE FROM tenant_user_permissions
          WHERE tenant_id = :tenant_id
            AND user_id = :user_id
        ");
        $delete->execute([
          'tenant_id' => $tenantId,
          'user_id' => $userId,
        ]);

        if (!empty($permissionIds))
          {
          $insert = $this->db->prepare("
            INSERT INTO tenant_user_permissions
              (tenant_id, user_id, permission_id, created, modified)
            VALUES
              (:tenant_id, :user_id, :permission_id, :created, :modified)
          ");

          foreach ($permissionIds as $permissionId)
            {
            $permissionId = (int) $permissionId;
            if ($permissionId <= 0)
              {
              continue;
              }

            $insert->execute([
              'tenant_id' => $tenantId,
              'user_id' => $userId,
              'permission_id' => $permissionId,
              'created' => $now,
              'modified' => $now,
            ]);
            }
        }

        $this->db->commit();
        }
      catch (\Throwable $e)
        {
        $this->db->rollBack();
        throw $e;
        }
      }
  }

?>