export type AuthPermission = {
  module: string | null;
  action: string | null;
};

export type AuthCompany = {
  id: number;
  name: string;
  legal_name: string | null;
  email: string | null;
  phone: string | null;
  status: number;
};

export type AuthBranch = {
  id: number;
  name: string;
  phone: string | null;
  address: string | null;
  status: number;
};

export type AuthRole = {
  id: number;
  name: string;
  description: string | null;
  permissions: AuthPermission[];
};

export type AuthUser = {
  id: number;
  uuid: string;
  username: string;
  full_name: string | null;
  email: string | null;
  phone: string | null;
  status: number;
  last_login: string | null;
  company: AuthCompany | null;
  branch: AuthBranch | null;
  role: AuthRole | null;
};

export type LoginCredentials = {
  username: string;
  password: string;
  remember: boolean;
};

export type LoginResponse = {
  message: string;
  token_type: string;
  token: string;
  expires_at: string;
  user: AuthUser;
};

export type MeResponse = {
  user: AuthUser;
};

export type AuthSession = {
  token: string;
  tokenType: string;
  expiresAt: string;
  user: AuthUser;
};
