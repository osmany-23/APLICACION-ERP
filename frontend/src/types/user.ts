export type UserRoleRef = {
  id: number;
  name: string;
};

export type UserEmployeeRef = {
  id: number;
  code: string;
  full_name: string;
};

export type ManagedUser = {
  id: number;
  uuid: string;
  username: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  branch_id: number | null;
  role_id: number | null;
  role: UserRoleRef | null;
  employee: UserEmployeeRef | null;
  status: number;
  status_label: string;
  has_pin: boolean;
  pin_generated_at: string | null;
  can_generate_pin: boolean;
  last_login: string | null;
};

export type UserListResponse = {
  data: ManagedUser[];
};

export type UserSaveResponse = {
  message?: string;
  item: ManagedUser;
};

export type GeneratePinResponse = {
  message: string;
  pin: string;
  pin_generated_at: string;
};
