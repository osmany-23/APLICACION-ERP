export type RolePermission = {
  id: number;
  module_name: string;
  action_name: string;
};

export type Role = {
  id: number;
  name: string;
  description: string | null;
  users_count: number;
  permissions: RolePermission[];
  permission_ids: number[];
};

export type RoleListResponse = {
  data: Role[];
};

export type RoleSaveResponse = {
  message?: string;
  item: Role;
};
