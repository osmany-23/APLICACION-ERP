export type Country = {
  id: number;
  iso2: string;
  iso3: string | null;
  name: string;
  phone_code: string | null;
};

export type CountryListResponse = {
  data: Country[];
};
