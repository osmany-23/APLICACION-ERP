export type PermissionEntry = {
  id: number;
  module_name: string;
  action_name: string;
  roles: string[];
};

export type PermissionGroup = {
  module_name: string;
  permissions: PermissionEntry[];
};

export type PermissionListResponse = {
  data: PermissionEntry[];
  grouped: PermissionGroup[];
};
