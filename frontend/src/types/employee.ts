export type EmployeeStatus = 'ACTIVE' | 'INACTIVE' | 'TERMINATED';

export type Department = {
  id: number;
  name: string;
  manager_employee_id: number | null;
  manager_name: string | null;
  status: boolean;
  employees_count: number;
};

export type Position = {
  id: number;
  name: string;
  description: string | null;
  department_id: number | null;
  department_name: string | null;
  base_salary: number | null;
  status: boolean;
  employees_count: number;
};

export type EmployeeUserRef = {
  id: number;
  username: string;
  status: number;
};

export type Employee = {
  id: number;
  code: string;
  first_name: string;
  last_name: string;
  full_name: string;
  phone: string | null;
  email: string | null;
  branch_id: number | null;
  department_id: number | null;
  department_name: string | null;
  position_id: number | null;
  position_name: string | null;
  salary: number | null;
  hire_date: string | null;
  termination_date: string | null;
  status: EmployeeStatus;
  status_label: string;
  user_id: number | null;
  user: EmployeeUserRef | null;
};

export type DepartmentListResponse = { data: Department[] };
export type PositionListResponse = { data: Position[] };
export type EmployeeListResponse = { data: Employee[] };

export type DepartmentSaveResponse = { message?: string; item: Department };
export type PositionSaveResponse = { message?: string; item: Position };
export type EmployeeSaveResponse = { message?: string; item: Employee };
