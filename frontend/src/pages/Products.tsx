import {
  ChangeEvent,
  FormEvent,
  ReactNode,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import {
  FiAlertCircle,
  FiBarChart2,
  FiBox,
  FiChevronDown,
  FiChevronLeft,
  FiChevronRight,
  FiChevronsLeft,
  FiChevronsRight,
  FiDollarSign,
  FiDownload,
  FiEdit2,
  FiEye,
  FiFilePlus,
  FiFilter,
  FiImage,
  FiPackage,
  FiPlus,
  FiRefreshCw,
  FiSave,
  FiSearch,
  FiTrash2,
  FiTrendingUp,
  FiUpload,
  FiX,
} from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { SwitchField } from '../components/SwitchField';
import ActionsMenu from '../components/ActionsMenu';
import ProductImagesManager, {
  ProductImageMeta,
  StagedImage,
  revokeStagedImages,
} from '../components/ProductImagesManager';

type ProductStatus = 'Activo' | 'Inactivo';
type DialogMode = 'create' | 'edit' | 'kardex' | null;
type TaxType = 'EXEMPT' | 'TAXABLE';
type MaxDiscountType = 'PERCENTAGE' | 'FIXED';
type InventoryStatus =
  | 'RECEIVED'
  | 'PENDING_RECEIPT'
  | 'IN_TRANSIT'
  | 'RESERVED';

export type ProductRecord = {
  id: number;
  companyId: number | null;
  company: string;
  imageUrl: string | null;
  images: ProductImageMeta[];
  name: string;
  fullName: string;
  code: string;
  barcode: string;
  supplierId: number | null;
  supplier: string;
  categoryId: number | null;
  category: string;
  subcategoryId: number | null;
  subcategory: string;
  brandId: number | null;
  brand: string;
  unitId: number | null;
  unit: string;
  purchaseUnitId: number | null;
  purchaseUnit: string;
  saleUnitId: number | null;
  saleUnit: string;
  conversionFactor: number;
  salePrice: number;
  salePriceWithTax: number;
  taxType: TaxType;
  taxPercentage: number;
  maxDiscountType: MaxDiscountType | null;
  maxDiscountValue: number | null;
  cost: number;
  stock: number;
  minStock: number;
  maxStock: number | null;
  allowNegativeStock: boolean;
  managesLots: boolean;
  // Ya no es un toggle propio: se deduce en el backend de si el lote trae
  // fecha de vencimiento cargada. Se mantiene aqui como dato de solo
  // lectura (por ejemplo para mostrarlo en el detalle del producto).
  managesExpiration: boolean;
  lotNumber: string;
  manufacturingDate: string;
  expirationDate: string;
  expirationAlertDays: number | null;
  lotPurchasePrice: number | null;
  inventoryStatus: InventoryStatus;
  warehouseId: number | null;
  warehouse: string;
  branchId: number | null;
  branch: string;
  status: ProductStatus;
  description: string;
  model: string;
  // Formato libre a proposito (sin patron fijo): un producto se mide
  // "30x34x12", otro como "Altura: 30cm, Largo: 66cm, Grosor: 5cm" — no
  // tiene sentido forzar un unico formato para todo el catalogo.
  measurement: string;
  physicalLocation: string;
  isInventory: boolean;
  // Ya no es un campo que el usuario active a mano: se deduce de
  // isInventory/isKit (ver productData() en el backend). Se conserva de
  // solo lectura porque el detalle de producto lo usa para mostrar
  // "Servicio" como tipo de producto.
  isService: boolean;
  isKit: boolean;
  allowPurchase: boolean;
  notes: string;
  observations: string;
  hasWarranty: boolean;
  warrantyDuration: number | null;
  warrantyPeriodUnit: WarrantyPeriodUnit;
  warrantyType: string | null;
  priceTiers: ProductPriceTierApiRecord[];
  stockByBranch: ProductStockByBranch[];
  kitItems: ProductKitItemApiRecord[];
};

export type WarrantyPeriodUnit = 'DAYS' | 'MONTHS' | 'YEARS';

export type ProductKitItemApiRecord = {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
};

export type ProductStockByBranch = {
  branch_id: number | null;
  branch_name: string;
  quantity: number;
  available_quantity: number;
};

type ProductDraft = {
  companyId: string;
  company: string;
  name: string;
  fullName: string;
  code: string;
  barcode: string;
  supplierId: string;
  supplier: string;
  categoryId: string;
  category: string;
  subcategoryId: string;
  subcategory: string;
  brandId: string;
  brand: string;
  unitId: string;
  unit: string;
  purchaseUnitId: string;
  purchaseUnit: string;
  saleUnitId: string;
  saleUnit: string;
  conversionFactor: string;
  salePrice: string;
  taxType: TaxType;
  taxPercentage: string;
  maxDiscountType: MaxDiscountType | '';
  maxDiscountValue: string;
  cost: string;
  stock: string;
  minStock: string;
  maxStock: string;
  allowNegativeStock: boolean;
  managesLots: boolean;
  lotNumber: string;
  manufacturingDate: string;
  expirationDate: string;
  expirationAlertDays: string;
  lotPurchasePrice: string;
  inventoryStatus: InventoryStatus;
  warehouseId: string;
  warehouse: string;
  branchId: string;
  branch: string;
  // Solo se usa al crear un producto, y solo si el usuario tiene el
  // permiso 'products'/'administrar' (rol Administrador o equivalente):
  // deja dar de alta el mismo stock inicial en varios almacenes/sucursales
  // a la vez, en vez de uno solo. Ver canManageMultipleBranches.
  warehouseIds: string[];
  status: ProductStatus;
  description: string;
  model: string;
  // Formato libre a proposito (sin patron fijo): un producto se mide
  // "30x34x12", otro como "Altura: 30cm, Largo: 66cm, Grosor: 5cm" — no
  // tiene sentido forzar un unico formato para todo el catalogo.
  measurement: string;
  physicalLocation: string;
  isInventory: boolean;
  isKit: boolean;
  allowPurchase: boolean;
  notes: string;
  observations: string;
  imageUrl: string;
  hasWarranty: boolean;
  warrantyDuration: string;
  warrantyPeriodUnit: WarrantyPeriodUnit;
  warrantyType: string;
  priceTiers: PriceTierDraft[];
  kitItems: KitItemDraft[];
};

// Fila del editor de "Componentes del combo/kit": que otro producto de la
// empresa entra y en que cantidad. Igual que PriceTierDraft, todo como
// string para que los inputs numericos queden controlados.
type KitItemDraft = {
  key: string;
  productId: string;
  quantity: string;
};

export type ProductApiRecord = {
  id: number | string;
  company_id?: number | string | null;
  company?: string | null;
  image_url: string | null;
  images?: ProductImageMeta[] | null;
  name: string | null;
  full_name?: string | null;
  code: string | null;
  barcode?: string | null;
  supplier_id?: number | string | null;
  supplier?: string | null;
  category_id?: number | string | null;
  category: string | null;
  subcategory_id?: number | string | null;
  subcategory?: string | null;
  brand_id?: number | string | null;
  brand: string | null;
  unit_id?: number | string | null;
  unit: string | null;
  purchase_unit_id?: number | string | null;
  purchase_unit?: string | null;
  sale_unit_id?: number | string | null;
  sale_unit?: string | null;
  conversion_factor?: number | string | null;
  sale_price: number | string | null;
  sale_price_with_tax?: number | string | null;
  tax_type?: string | null;
  tax_percentage?: number | string | null;
  max_discount_type?: string | null;
  max_discount_value?: number | string | null;
  cost: number | string | null;
  stock: number | string | null;
  initial_stock?: number | string | null;
  minimum_stock: number | string | null;
  maximum_stock?: number | string | null;
  allow_negative_stock?: boolean | number | string | null;
  manages_lots?: boolean | number | string | null;
  manages_expiration?: boolean | number | string | null;
  expiration_alert_days?: number | string | null;
  lot_number?: string | null;
  manufacturing_date?: string | null;
  expiration_date?: string | null;
  lot_purchase_price?: number | string | null;
  inventory_status?: InventoryStatus | null;
  warehouse_id?: number | string | null;
  warehouse?: string | null;
  branch_id?: number | string | null;
  branch?: string | null;
  status: number | string | null;
  status_label?: string | null;
  description?: string | null;
  model?: string | null;
  measurement?: string | null;
  physical_location?: string | null;
  is_inventory?: boolean | number | string | null;
  is_service?: boolean | number | string | null;
  is_kit?: boolean | number | string | null;
  allow_purchase?: boolean | number | string | null;
  notes?: string | null;
  observations?: string | null;
  has_warranty?: boolean | number | string | null;
  warranty_duration?: number | string | null;
  warranty_period_unit?: WarrantyPeriodUnit | null;
  warranty_type?: string | null;
  price_tiers?: ProductPriceTierApiRecord[] | null;
  stock_by_branch?: ProductStockByBranch[] | null;
  kit_items?: ProductKitItemApiRecord[] | null;
};

type ProductPriceTierApiRecord = {
  id: number | string;
  price_list_id: number | string;
  price_list_name: string;
  min_quantity: number | string;
  price: number | string;
  is_active: boolean;
};

type ProductsResponse = {
  data: ProductApiRecord[];
};

type CatalogOption = {
  id: number | string;
  name: string;
  short_name?: string | null;
  parent_id?: number | string | null;
  parent_name?: string | null;
  branch_id?: number | string | null;
  branch_name?: string | null;
};

type PriceTypeOption = {
  id: number;
  name: string;
  is_active: boolean;
};

type CatalogOptions = {
  brands: CatalogOption[];
  categories: CatalogOption[];
  subcategories: CatalogOption[];
  units: CatalogOption[];
  suppliers: CatalogOption[];
  warehouses: CatalogOption[];
  branches: CatalogOption[];
  priceTypes: PriceTypeOption[];
};

type CatalogResponse = {
  data: CatalogOption[];
};

type PriceTypesResponse = {
  data: PriceTypeOption[];
};

// Fila del editor de "precios por volumen" en el formulario de producto.
// Todo como string (igual que el resto de ProductDraft) para que los
// inputs numericos queden controlados sin pelear con NaN mientras el
// usuario escribe.
type PriceTierDraft = {
  key: string;
  // El usuario escribe el nombre libremente (no elige de un catalogo
  // cerrado) — el backend busca/crea el "tipo de precio" por ese nombre.
  // Ver <datalist> en el formulario: catalogs.priceTypes alimenta las
  // sugerencias con los nombres ya usados antes.
  priceListName: string;
  minQuantity: string;
  price: string;
};

type ProductMutationResponse = {
  message?: string;
  product: ProductApiRecord;
};

export type ProductShowResponse = {
  product?: ProductApiRecord;
  data?: ProductApiRecord;
};

type ProductDeleteResponse = {
  message: string;
  deleted: boolean;
};

type KardexApiRow = {
  id: number | string;
  date: string | null;
  detail: string | null;
  input: number | string | null;
  output: number | string | null;
  balance: number | string | null;
  warehouse?: string | null;
};

type KardexRow = {
  id: number;
  date: string;
  detail: string;
  input: number;
  output: number;
  balance: number;
  warehouse: string;
};

type KardexResponse = {
  data: KardexApiRow[];
};

const ITEMS_PER_PAGE = 5;

const emptyDraft: ProductDraft = {
  companyId: '',
  company: '',
  name: '',
  fullName: '',
  code: '',
  barcode: '',
  supplierId: '',
  supplier: '',
  categoryId: '',
  category: 'General',
  subcategoryId: '',
  subcategory: '',
  brandId: '',
  brand: 'Generica',
  unitId: '',
  unit: 'UND',
  purchaseUnitId: '',
  purchaseUnit: 'UND',
  saleUnitId: '',
  saleUnit: 'UND',
  conversionFactor: '1',
  salePrice: '',
  taxType: 'EXEMPT',
  taxPercentage: '0',
  maxDiscountType: '',
  maxDiscountValue: '',
  cost: '',
  stock: '0',
  minStock: '0',
  maxStock: '',
  allowNegativeStock: false,
  managesLots: false,
  lotNumber: '',
  manufacturingDate: '',
  expirationDate: '',
  expirationAlertDays: '',
  lotPurchasePrice: '',
  inventoryStatus: 'RECEIVED',
  warehouseId: '',
  warehouse: '',
  branchId: '',
  branch: '',
  warehouseIds: [],
  status: 'Activo',
  description: '',
  model: '',
  measurement: '',
  physicalLocation: '',
  isInventory: true,
  isKit: false,
  allowPurchase: true,
  notes: '',
  observations: '',
  imageUrl: '',
  hasWarranty: false,
  warrantyDuration: '',
  warrantyPeriodUnit: 'DAYS',
  warrantyType: '',
  priceTiers: [],
  kitItems: [],
};

const emptyCatalogs: CatalogOptions = {
  brands: [],
  categories: [],
  subcategories: [],
  priceTypes: [],
  units: [],
  suppliers: [],
  warehouses: [],
  branches: [],
};

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

const selectClass =
  'h-12 w-full appearance-none rounded-lg border border-stroke bg-white px-4 pr-10 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

const textareaClass =
  'min-h-24 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function readNumber(value: unknown) {
  const parsed = Number(value ?? 0);

  return Number.isFinite(parsed) ? parsed : 0;
}

function parseNumber(value: string) {
  const parsed = Number(value.replace(/,/g, '').trim());

  return Number.isFinite(parsed) ? parsed : 0;
}

function readBoolean(value: unknown, defaultValue = false) {
  if (value === null || value === undefined || value === '') {
    return defaultValue;
  }

  if (typeof value === 'boolean') {
    return value;
  }

  return ['1', 'true', 'si', 'sí', 'yes'].includes(
    String(value).trim().toLowerCase(),
  );
}

function normalizeTaxType(value: unknown): TaxType {
  return ['taxable', 'gravado'].includes(String(value || '').toLowerCase())
    ? 'TAXABLE'
    : 'EXEMPT';
}

function normalizeMaxDiscountType(value: unknown): MaxDiscountType | null {
  return value === 'PERCENTAGE' || value === 'FIXED' ? value : null;
}

function formatOptionName(option?: CatalogOption | null) {
  if (!option) {
    return '';
  }

  return option.short_name || option.name || '';
}

function taxLabel(value: TaxType) {
  return value === 'TAXABLE' ? 'Gravado' : 'Exento';
}

function normalizeText(value: string) {
  return value.trim().toLowerCase();
}

function normalizeKey(value: string) {
  return normalizeText(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[\s-]+/g, '_');
}

function normalizeStatus(value: string): ProductStatus {
  return normalizeText(value).includes('inactivo') || value === '0'
    ? 'Inactivo'
    : 'Activo';
}

export function formatCurrency(value: number) {
  return `C$${value.toLocaleString('es-NI', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function formatQuantity(value: number) {
  return value.toLocaleString('es-NI', {
    maximumFractionDigits: Number.isInteger(value) ? 0 : 2,
  });
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors
      ? Object.values(error.errors).flat()[0]
      : null;

    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

export function mapProduct(product: ProductApiRecord): ProductRecord {
  const status =
    product.status_label === 'Inactivo' || Number(product.status) === 0
      ? 'Inactivo'
      : 'Activo';

  return {
    id: Number(product.id),
    companyId: product.company_id ? Number(product.company_id) : null,
    company: product.company || '',
    imageUrl: product.image_url || null,
    images: product.images || [],
    name: product.name || 'Producto sin nombre',
    fullName: product.full_name || '',
    code: product.code || '',
    barcode: product.barcode || '',
    supplierId: product.supplier_id ? Number(product.supplier_id) : null,
    supplier: product.supplier || 'Sin proveedor',
    categoryId: product.category_id ? Number(product.category_id) : null,
    category: product.category || 'General',
    subcategoryId: product.subcategory_id ? Number(product.subcategory_id) : null,
    subcategory: product.subcategory || '',
    brandId: product.brand_id ? Number(product.brand_id) : null,
    brand: product.brand || 'Generica',
    unitId: product.unit_id ? Number(product.unit_id) : null,
    unit: product.unit || 'UND',
    purchaseUnitId: product.purchase_unit_id
      ? Number(product.purchase_unit_id)
      : null,
    purchaseUnit: product.purchase_unit || product.unit || 'UND',
    saleUnitId: product.sale_unit_id ? Number(product.sale_unit_id) : null,
    saleUnit: product.sale_unit || product.unit || 'UND',
    conversionFactor: readNumber(product.conversion_factor || 1),
    salePrice: readNumber(product.sale_price),
    salePriceWithTax: readNumber(product.sale_price_with_tax),
    taxType: normalizeTaxType(product.tax_type),
    taxPercentage: readNumber(product.tax_percentage),
    maxDiscountType: normalizeMaxDiscountType(product.max_discount_type),
    maxDiscountValue:
      product.max_discount_value === null || product.max_discount_value === undefined
        ? null
        : readNumber(product.max_discount_value),
    cost: readNumber(product.cost),
    stock: readNumber(product.initial_stock ?? product.stock),
    minStock: readNumber(product.minimum_stock),
    maxStock:
      product.maximum_stock === null || product.maximum_stock === undefined
        ? null
        : readNumber(product.maximum_stock),
    allowNegativeStock: readBoolean(product.allow_negative_stock),
    managesLots: readBoolean(product.manages_lots),
    managesExpiration: readBoolean(product.manages_expiration),
    lotNumber: product.lot_number || '',
    manufacturingDate: product.manufacturing_date || '',
    expirationDate: product.expiration_date || '',
    expirationAlertDays:
      product.expiration_alert_days === null || product.expiration_alert_days === undefined
        ? null
        : Number(product.expiration_alert_days),
    lotPurchasePrice:
      product.lot_purchase_price === null || product.lot_purchase_price === undefined
        ? null
        : readNumber(product.lot_purchase_price),
    inventoryStatus: product.inventory_status || 'RECEIVED',
    warehouseId: product.warehouse_id ? Number(product.warehouse_id) : null,
    warehouse: product.warehouse || 'Sin almacen',
    branchId: product.branch_id ? Number(product.branch_id) : null,
    branch: product.branch || 'Sin sucursal',
    status,
    description: product.description || '',
    model: product.model || '',
    measurement: product.measurement || '',
    physicalLocation: product.physical_location || '',
    isInventory: readBoolean(product.is_inventory, true),
    isService: readBoolean(product.is_service),
    isKit: readBoolean(product.is_kit),
    allowPurchase: readBoolean(product.allow_purchase, true),
    notes: product.notes || '',
    observations: product.observations || '',
    hasWarranty: readBoolean(product.has_warranty),
    warrantyDuration:
      product.warranty_duration === null || product.warranty_duration === undefined
        ? null
        : readNumber(product.warranty_duration),
    warrantyPeriodUnit: product.warranty_period_unit || 'DAYS',
    warrantyType: product.warranty_type || null,
    priceTiers: product.price_tiers || [],
    stockByBranch: product.stock_by_branch || [],
    kitItems: product.kit_items || [],
  };
}

function mapCatalogOption(option: CatalogOption): CatalogOption {
  return {
    ...option,
    id: Number(option.id),
    parent_id: option.parent_id ? Number(option.parent_id) : null,
    branch_id: option.branch_id ? Number(option.branch_id) : null,
  };
}

function findOptionId(options: CatalogOption[], value: string) {
  const search = normalizeText(value);

  if (!search) {
    return '';
  }

  const option = options.find(
    (item) =>
      normalizeText(item.name) === search ||
      normalizeText(item.short_name || '') === search,
  );

  return option?.id ? String(option.id) : '';
}

function toDraft(product: ProductRecord): ProductDraft {
  return {
    companyId: product.companyId ? String(product.companyId) : '',
    company: product.company,
    name: product.name,
    fullName: product.fullName,
    code: product.code,
    barcode: product.barcode,
    supplierId: product.supplierId ? String(product.supplierId) : '',
    supplier: product.supplier,
    categoryId: product.categoryId ? String(product.categoryId) : '',
    category: product.category,
    subcategoryId: product.subcategoryId ? String(product.subcategoryId) : '',
    subcategory: product.subcategory,
    brandId: product.brandId ? String(product.brandId) : '',
    brand: product.brand,
    unitId: product.unitId ? String(product.unitId) : '',
    unit: product.unit,
    purchaseUnitId: product.purchaseUnitId ? String(product.purchaseUnitId) : '',
    purchaseUnit: product.purchaseUnit,
    saleUnitId: product.saleUnitId ? String(product.saleUnitId) : '',
    saleUnit: product.saleUnit,
    conversionFactor: String(product.conversionFactor || 1),
    salePrice: String(product.salePrice),
    taxType: product.taxType,
    taxPercentage: String(product.taxPercentage),
    maxDiscountType: product.maxDiscountType ?? '',
    maxDiscountValue: product.maxDiscountValue === null ? '' : String(product.maxDiscountValue),
    cost: String(product.cost),
    stock: String(product.stock),
    minStock: String(product.minStock),
    maxStock: product.maxStock === null ? '' : String(product.maxStock),
    allowNegativeStock: product.allowNegativeStock,
    managesLots: product.managesLots,
    lotNumber: product.lotNumber,
    manufacturingDate: product.manufacturingDate,
    expirationDate: product.expirationDate,
    expirationAlertDays: product.expirationAlertDays === null ? '' : String(product.expirationAlertDays),
    lotPurchasePrice: product.lotPurchasePrice === null ? '' : String(product.lotPurchasePrice),
    inventoryStatus: product.inventoryStatus,
    warehouseId: product.warehouseId ? String(product.warehouseId) : '',
    warehouse: product.warehouse,
    branchId: product.branchId ? String(product.branchId) : '',
    branch: product.branch,
    warehouseIds: [],
    status: product.status,
    description: product.description,
    model: product.model,
    measurement: product.measurement,
    physicalLocation: product.physicalLocation,
    isInventory: product.isInventory,
    isKit: product.isKit,
    allowPurchase: product.allowPurchase,
    notes: product.notes,
    observations: product.observations,
    imageUrl: product.imageUrl || '',
    hasWarranty: product.hasWarranty,
    warrantyDuration: product.warrantyDuration === null ? '' : String(product.warrantyDuration),
    warrantyPeriodUnit: product.warrantyPeriodUnit,
    warrantyType: product.warrantyType || '',
    priceTiers: product.priceTiers.map((tier) => ({
      key: String(tier.id),
      priceListName: tier.price_list_name,
      minQuantity: String(tier.min_quantity),
      price: String(tier.price),
    })),
    kitItems: product.kitItems.map((item) => ({
      key: String(item.id),
      productId: String(item.product_id),
      quantity: String(item.quantity),
    })),
  };
}

function uniqueValues(products: ProductRecord[], field: keyof ProductRecord) {
  return Array.from(
    new Set(products.map((product) => String(product[field])).filter(Boolean)),
  ).sort((a, b) => a.localeCompare(b));
}

function escapeCsv(value: string | number) {
  const text = String(value);

  if (/[",\n]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }

  return text;
}

function parseCsvLine(line: string) {
  const values: string[] = [];
  let current = '';
  let insideQuotes = false;

  for (let index = 0; index < line.length; index += 1) {
    const char = line[index];
    const nextChar = line[index + 1];

    if (char === '"' && insideQuotes && nextChar === '"') {
      current += '"';
      index += 1;
      continue;
    }

    if (char === '"') {
      insideQuotes = !insideQuotes;
      continue;
    }

    if (char === ',' && !insideQuotes) {
      values.push(current);
      current = '';
      continue;
    }

    current += char;
  }

  values.push(current);
  return values.map((value) => value.trim());
}

function parseProductCsv(text: string) {
  const lines = text
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  if (lines.length < 2) {
    return [];
  }

  const headers = parseCsvLine(lines[0]).map(normalizeKey);

  return lines
    .slice(1)
    .map((line) => {
      const cells = parseCsvLine(line);
      const pick = (...names: string[]) => {
        const index = headers.findIndex((header) => names.includes(header));

        return index >= 0 ? cells[index] || '' : '';
      };

      const name = pick('nombre', 'name');
      const code = pick('codigo', 'code');
      const cost = pick('costo', 'cost') || '0';
      const salePrice =
        pick('precio_venta', 'precio', 'sale_price') || cost || '0';

      if (!name || !code) {
        return null;
      }

      return {
        ...emptyDraft,
        name: name.toUpperCase(),
        code,
        category: pick('categoria', 'category') || 'General',
        subcategory: pick('subcategoria', 'subcategory'),
        brand: (pick('marca', 'brand') || 'Generica').toUpperCase(),
        unit: (pick('unidad', 'unit') || 'UND').toUpperCase(),
        salePrice,
        cost,
        stock: pick('stock') || '0',
        minStock: pick('stock_minimo', 'minimum_stock') || '0',
        status: normalizeStatus(pick('estado', 'status') || 'Activo'),
        description: pick('descripcion', 'description'),
        imageUrl: pick('imagen', 'image_url'),
      };
    })
    .filter((product): product is ProductDraft => product !== null);
}

function Field({
  children,
  error,
  label,
  required = false,
  className = '',
}: {
  children: ReactNode;
  error?: string;
  label: string;
  required?: boolean;
  className?: string;
}) {
  return (
    <label className={`block ${className}`}>
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">
        {label}
        {required && <span className="text-red-500"> *</span>}
      </span>
      {children}
      {error && <span className="mt-2 block text-xs font-semibold text-red-500">{error}</span>}
    </label>
  );
}

function SelectWrap({ children }: { children: ReactNode }) {
  return (
    <div className="relative">
      {children}
      <FiChevronDown className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-black dark:text-white" />
    </div>
  );
}

function FormCard({
  children,
  title,
}: {
  children: ReactNode;
  title: string;
}) {
  return (
    <section className="rounded-lg border border-stroke p-5 dark:border-strokedark">
      <h3 className="mb-5 text-base font-black uppercase text-black dark:text-white">
        {title}
      </h3>
      {children}
    </section>
  );
}

function ToggleField({
  checked,
  label,
  onChange,
}: {
  checked: boolean;
  label: string;
  onChange: (checked: boolean) => void;
}) {
  return (
    <label className="flex h-12 items-center justify-between gap-3 rounded-lg border border-stroke bg-white px-4 text-sm font-semibold text-black dark:border-strokedark dark:bg-boxdark dark:text-white">
      <span>{label}</span>
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="h-5 w-5 accent-primary"
      />
    </label>
  );
}

function ProductPicture({
  onOpen,
  product,
}: {
  onOpen?: () => void;
  product: ProductRecord;
}) {
  if (product.imageUrl) {
    return (
      <button
        type="button"
        onClick={onOpen}
        className="h-16 w-16 overflow-hidden rounded-full border border-stroke bg-slate-100 transition hover:scale-105 hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-strokedark"
        aria-label={`Ver imagen de ${product.name}`}
      >
        <img
          src={product.imageUrl}
          alt={product.name}
          className="h-full w-full object-cover"
        />
      </button>
    );
  }

  return (
    <div className="flex h-16 w-16 items-center justify-center rounded-full border border-stroke bg-[#F3F6FB] text-slate-400 dark:border-strokedark dark:bg-meta-4">
      <FiImage className="h-7 w-7" />
    </div>
  );
}

const Products = () => {
  const { token, user } = useAuth();
  // Igual criterio que el backend (ProductController::userCanManageMultipleBranches):
  // el rol "Administrador" siempre puede, cualquier otro rol necesita el
  // permiso explicito 'products'/'administrar'. Solo controla si se
  // MUESTRA la opcion — el backend vuelve a validar todo por su cuenta.
  const canManageMultipleBranches = Boolean(
    user?.role?.name?.toLowerCase() === 'administrador' ||
      user?.role?.permissions?.some(
        (permission) => permission.module === 'products' && permission.action === 'administrar',
      ),
  );
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [products, setProducts] = useState<ProductRecord[]>([]);
  const [catalogs, setCatalogs] = useState<CatalogOptions>(emptyCatalogs);
  const [loadingProducts, setLoadingProducts] = useState(true);
  const [loadingCatalogs, setLoadingCatalogs] = useState(true);
  const [pageError, setPageError] = useState('');
  const [notice, setNotice] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('Todos');
  const [categoryFilter, setCategoryFilter] = useState('Todas');
  const [brandFilter, setBrandFilter] = useState('Todas');
  const [unitFilter, setUnitFilter] = useState('Todas');
  const [filtersOpen, setFiltersOpen] = useState(false);
  const activeFilterCount = [
    statusFilter !== 'Todos',
    categoryFilter !== 'Todas',
    brandFilter !== 'Todas',
    unitFilter !== 'Todas',
  ].filter(Boolean).length;
  const [supplierFilter, setSupplierFilter] = useState('Todos');
  const [warehouseFilter, setWarehouseFilter] = useState('Todos');
  const [currentPage, setCurrentPage] = useState(1);
  const [actionProductId, setActionProductId] = useState<number | null>(null);
  const [dialogMode, setDialogMode] = useState<DialogMode>(null);
  const [selectedProductId, setSelectedProductId] = useState<number | null>(
    null,
  );
  const [draft, setDraft] = useState<ProductDraft>(emptyDraft);
  const [productImages, setProductImages] = useState<ProductImageMeta[]>([]);
  const [stagedImages, setStagedImages] = useState<StagedImage[]>([]);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [importing, setImporting] = useState(false);
  const [kardexRows, setKardexRows] = useState<KardexRow[]>([]);
  const [kardexLoading, setKardexLoading] = useState(false);
  const [kardexError, setKardexError] = useState('');
  const [imagePreviewProduct, setImagePreviewProduct] =
    useState<ProductRecord | null>(null);

  const loadProducts = useCallback(async () => {
    if (!token) {
      setProducts([]);
      setLoadingProducts(false);
      return;
    }

    setLoadingProducts(true);
    setPageError('');

    try {
      const response = await apiRequest<ProductsResponse>('/products', {}, token);
      setProducts(response.data.map(mapProduct));
    } catch (error) {
      setPageError(getErrorMessage(error));
      setProducts([]);
    } finally {
      setLoadingProducts(false);
    }
  }, [token]);

  async function handleToggleStatus(product: ProductRecord) {
    if (!token) return;

    try {
      await apiRequest(
        `/products/${product.id}/status`,
        { method: 'PATCH', body: JSON.stringify({ status: product.status !== 'Activo' }) },
        token,
      );
      await loadProducts();
    } catch (toggleError) {
      // El backend responde 422 con un mensaje claro cuando el producto
      // esta en una venta/compra abierta (DRAFT/PENDING) y no se puede
      // desactivar hasta finalizar o anular esa transaccion.
      setPageError(getErrorMessage(toggleError));
    }
  }

  const loadCatalogs = useCallback(async () => {
    if (!token) {
      setCatalogs(emptyCatalogs);
      setLoadingCatalogs(false);
      return;
    }

    setLoadingCatalogs(true);

    try {
      const [
        brands,
        categories,
        subcategories,
        units,
        suppliers,
        warehouses,
        branches,
        priceTypes,
      ] = await Promise.all([
        apiRequest<CatalogResponse>('/catalogs/brands', {}, token),
        apiRequest<CatalogResponse>('/catalogs/categories', {}, token),
        apiRequest<CatalogResponse>('/catalogs/subcategories', {}, token),
        apiRequest<CatalogResponse>('/catalogs/units', {}, token),
        apiRequest<CatalogResponse>('/relations/suppliers', {}, token),
        apiRequest<CatalogResponse>('/relations/warehouses', {}, token),
        apiRequest<CatalogResponse>('/relations/branches', {}, token),
        apiRequest<PriceTypesResponse>('/settings/price-types', {}, token),
      ]);

      setCatalogs({
        brands: brands.data.map(mapCatalogOption),
        categories: categories.data.map(mapCatalogOption),
        subcategories: subcategories.data.map(mapCatalogOption),
        units: units.data.map(mapCatalogOption),
        suppliers: suppliers.data.map(mapCatalogOption),
        warehouses: warehouses.data.map(mapCatalogOption),
        branches: branches.data.map(mapCatalogOption),
        priceTypes: priceTypes.data,
      });
    } catch (error) {
      setPageError(getErrorMessage(error));
      setCatalogs(emptyCatalogs);
    } finally {
      setLoadingCatalogs(false);
    }
  }, [token]);

  useEffect(() => {
    void loadProducts();
    void loadCatalogs();
  }, [loadCatalogs, loadProducts]);

  const selectedProduct = useMemo(
    () => products.find((product) => product.id === selectedProductId) || null,
    [products, selectedProductId],
  );

  // Candidatos para componer un combo/kit: cualquier producto de la
  // empresa, menos otros kits (no se permiten combos anidados, ver
  // validacion en el backend) y menos el producto que se esta editando
  // (no puede componerse a si mismo).
  const availableKitComponents = useMemo(
    () => products.filter((product) => !product.isKit && product.id !== selectedProductId),
    [products, selectedProductId],
  );

  const categories = useMemo(
    () => uniqueValues(products, 'category'),
    [products],
  );
  const brands = useMemo(() => uniqueValues(products, 'brand'), [products]);
  const units = useMemo(() => uniqueValues(products, 'unit'), [products]);
  const suppliers = useMemo(() => uniqueValues(products, 'supplier'), [products]);
  const warehouses = useMemo(
    () => uniqueValues(products, 'warehouse'),
    [products],
  );
  const availableSubcategories = useMemo(() => {
    const categoryId = Number(draft.categoryId || 0);

    return catalogs.subcategories.filter(
      (subcategory) => Number(subcategory.parent_id || 0) === categoryId,
    );
  }, [catalogs.subcategories, draft.categoryId]);
  const availableWarehouses = useMemo(() => {
    const branchId = Number(draft.branchId || 0);

    if (!branchId) {
      return catalogs.warehouses;
    }

    return catalogs.warehouses.filter(
      (warehouse) => Number(warehouse.branch_id || 0) === branchId,
    );
  }, [catalogs.warehouses, draft.branchId]);

  const filteredProducts = useMemo(() => {
    const search = normalizeText(searchTerm);

    return products.filter((product) => {
      const matchesSearch =
        !search ||
        [
          product.name,
          product.code,
          product.brand,
          product.category,
          product.subcategory,
          product.supplier,
          product.warehouse,
          product.branch,
          product.company,
          product.description,
        ]
          .map(normalizeText)
          .some((value) => value.includes(search));

      return (
        matchesSearch &&
        (statusFilter === 'Todos' || product.status === statusFilter) &&
        (categoryFilter === 'Todas' || product.category === categoryFilter) &&
        (brandFilter === 'Todas' || product.brand === brandFilter) &&
        (unitFilter === 'Todas' || product.unit === unitFilter) &&
        (supplierFilter === 'Todos' || product.supplier === supplierFilter) &&
        (warehouseFilter === 'Todos' || product.warehouse === warehouseFilter)
      );
    });
  }, [
    brandFilter,
    categoryFilter,
    products,
    searchTerm,
    statusFilter,
    supplierFilter,
    unitFilter,
    warehouseFilter,
  ]);

  const totalPages = Math.max(
    1,
    Math.ceil(filteredProducts.length / ITEMS_PER_PAGE),
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const paginatedProducts = useMemo(() => {
    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    return filteredProducts.slice(start, start + ITEMS_PER_PAGE);
  }, [currentPage, filteredProducts]);

  const activeCount = products.filter(
    (product) => product.status === 'Activo',
  ).length;
  const lowStockCount = products.filter(
    (product) => product.status === 'Activo' && product.stock <= product.minStock,
  ).length;
  const inventoryValue = products.reduce(
    (total, product) => total + product.stock * product.cost,
    0,
  );
  const marginProducts = products.filter((product) => product.salePrice > 0);
  const averageMargin =
    marginProducts.length === 0
      ? 0
      : marginProducts.reduce(
          (total, product) =>
            total +
            ((product.salePrice - product.cost) / product.salePrice) * 100,
          0,
        ) / marginProducts.length;

  const firstItem =
    filteredProducts.length === 0 ? 0 : (currentPage - 1) * ITEMS_PER_PAGE + 1;
  const lastItem = Math.min(
    filteredProducts.length,
    currentPage * ITEMS_PER_PAGE,
  );
  const previewSalePrice = parseNumber(draft.salePrice);
  const previewTaxPercentage =
    draft.taxType === 'TAXABLE' ? parseNumber(draft.taxPercentage) : 0;
  const previewTaxAmount = previewSalePrice * (previewTaxPercentage / 100);
  const previewFinalPrice = previewSalePrice + previewTaxAmount;
  const draftErrors = useMemo(() => {
    const errors: Partial<Record<keyof ProductDraft, string>> = {};
    const code = normalizeText(draft.code);
    const barcode = normalizeText(draft.barcode);
    const currentProductId =
      dialogMode === 'edit' ? selectedProductId || undefined : undefined;
    const duplicatedCode = products.some(
      (product) =>
        product.id !== currentProductId &&
        normalizeText(product.code) === code,
    );
    const duplicatedBarcode =
      barcode &&
      products.some(
        (product) =>
          product.id !== currentProductId &&
          normalizeText(product.barcode) === barcode,
      );
    const conversionFactor = parseNumber(draft.conversionFactor);
    const cost = parseNumber(draft.cost);
    const stock = parseNumber(draft.stock);
    const minStock = parseNumber(draft.minStock);
    const maxStock =
      draft.maxStock.trim() === '' ? null : parseNumber(draft.maxStock);
    const salePrice = parseNumber(draft.salePrice);
    const taxPercentage = parseNumber(draft.taxPercentage);

    if (!draft.name.trim()) errors.name = 'Ingresa el nombre corto.';
    if (!draft.code.trim()) errors.code = 'Ingresa el codigo interno.';
    if (duplicatedCode) errors.code = 'Este codigo ya existe.';
    if (duplicatedBarcode) errors.barcode = 'Este codigo de barras ya existe.';
    if (!draft.supplierId) errors.supplierId = 'Selecciona proveedor.';
    if (!draft.categoryId) errors.categoryId = 'Selecciona categoria.';
    if (!draft.brandId) errors.brandId = 'Selecciona marca.';
    if (!draft.purchaseUnitId) errors.purchaseUnitId = 'Selecciona unidad.';
    if (!draft.saleUnitId) errors.saleUnitId = 'Selecciona unidad.';
    const useMultiBranchInDraft = dialogMode === 'create' && canManageMultipleBranches;
    if (useMultiBranchInDraft) {
      if (draft.warehouseIds.length === 0) {
        errors.warehouseId = 'Selecciona al menos un almacen.';
      }
    } else if (!draft.warehouseId) {
      errors.warehouseId = 'Selecciona almacen.';
    }
    if (conversionFactor <= 0) {
      errors.conversionFactor = 'Debe ser mayor que cero.';
    }
    if (draft.cost.trim() === '' || cost < 0) errors.cost = 'Costo invalido.';
    if (draft.salePrice.trim() === '' || salePrice < 0) {
      errors.salePrice = 'Precio invalido.';
    }
    if (salePrice <= 0) {
      errors.salePrice = 'Debe ser mayor que cero para vender.';
    }
    if (draft.taxType === 'TAXABLE' && draft.taxPercentage.trim() === '') {
      errors.taxPercentage = 'Ingresa el porcentaje.';
    }
    if (draft.taxType === 'TAXABLE' && (taxPercentage < 0 || taxPercentage > 100)) {
      errors.taxPercentage = 'Debe estar entre 0 y 100.';
    }
    const maxDiscountValue =
      draft.maxDiscountValue.trim() === '' ? null : parseNumber(draft.maxDiscountValue);
    if (draft.maxDiscountType && maxDiscountValue === null) {
      errors.maxDiscountValue = 'Ingresa el valor del descuento maximo.';
    }
    if (maxDiscountValue !== null && maxDiscountValue < 0) {
      errors.maxDiscountValue = 'No puede ser negativo.';
    }
    if (draft.maxDiscountType === 'PERCENTAGE' && maxDiscountValue !== null && maxDiscountValue > 100) {
      errors.maxDiscountValue = 'Debe estar entre 0 y 100.';
    }
    if (stock < 0) errors.stock = 'No puede ser negativo.';
    if (minStock < 0) errors.minStock = 'No puede ser negativo.';
    if (maxStock !== null && maxStock < 0) errors.maxStock = 'No puede ser negativo.';
    if (maxStock !== null && minStock > maxStock) {
      errors.maxStock = 'Debe ser mayor o igual al minimo.';
    }
    if (draft.isKit) {
      const validItems = draft.kitItems.filter((item) => item.productId.trim() !== '');
      if (validItems.length === 0) {
        errors.kitItems = 'Selecciona al menos un producto para armar el combo/kit.';
      } else if (
        validItems.some((item) => item.quantity.trim() === '' || parseNumber(item.quantity) <= 0)
      ) {
        errors.kitItems = 'Ingresa una cantidad valida (mayor que cero) para cada componente.';
      } else if (new Set(validItems.map((item) => item.productId)).size !== validItems.length) {
        errors.kitItems = 'No repitas el mismo producto en varias filas del combo/kit.';
      }
    }
    if (draft.managesLots && !draft.lotNumber.trim()) {
      errors.lotNumber = 'Ingresa el lote.';
    }
    if (
      draft.managesLots &&
      draft.manufacturingDate &&
      draft.expirationDate &&
      draft.expirationDate < draft.manufacturingDate
    ) {
      errors.expirationDate = 'No puede ser anterior a la fecha de fabricacion.';
    }
    if (
      draft.managesLots &&
      draft.expirationAlertDays.trim() !== '' &&
      parseNumber(draft.expirationAlertDays) <= 0
    ) {
      errors.expirationAlertDays = 'Debe ser mayor que cero.';
    }
    if (draft.hasWarranty && (!draft.warrantyDuration.trim() || parseNumber(draft.warrantyDuration) <= 0)) {
      errors.warrantyDuration = 'Ingresa la duracion de la garantia.';
    }

    // Mismo criterio que el backend: se compara por nombre normalizado
    // (sin mayusculas ni espacios de sobra), ya que ahora el usuario lo
    // escribe libre en vez de elegir un id de una lista cerrada.
    const selectedPriceTypeNames = draft.priceTiers
      .map((tier) => tier.priceListName.trim().toLowerCase())
      .filter((name) => name !== '');
    if (new Set(selectedPriceTypeNames).size !== selectedPriceTypeNames.length) {
      errors.priceTiers = 'No repitas el mismo tipo de precio en varias filas.';
    }

    return errors;
  }, [dialogMode, draft, products, selectedProductId, canManageMultipleBranches]);

  function updateDraft<K extends keyof ProductDraft>(
    field: K,
    value: ProductDraft[K],
  ) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      [field]: value,
    }));
  }

  function toggleWarehouseSelection(warehouseId: string) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      warehouseIds: currentDraft.warehouseIds.includes(warehouseId)
        ? currentDraft.warehouseIds.filter((id) => id !== warehouseId)
        : [...currentDraft.warehouseIds, warehouseId],
    }));
  }

  function addPriceTier() {
    setDraft((currentDraft) => ({
      ...currentDraft,
      priceTiers: [
        ...currentDraft.priceTiers,
        { key: `new-${Date.now()}`, priceListName: '', minQuantity: '1', price: '' },
      ],
    }));
  }

  function updatePriceTier(key: string, patch: Partial<PriceTierDraft>) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      priceTiers: currentDraft.priceTiers.map((tier) =>
        tier.key === key ? { ...tier, ...patch } : tier,
      ),
    }));
  }

  function removePriceTier(key: string) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      priceTiers: currentDraft.priceTiers.filter((tier) => tier.key !== key),
    }));
  }

  function addKitItem() {
    setDraft((currentDraft) => ({
      ...currentDraft,
      kitItems: [
        ...currentDraft.kitItems,
        { key: `new-${Date.now()}`, productId: '', quantity: '1' },
      ],
    }));
  }

  function updateKitItem(key: string, patch: Partial<KitItemDraft>) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      kitItems: currentDraft.kitItems.map((item) =>
        item.key === key ? { ...item, ...patch } : item,
      ),
    }));
  }

  function removeKitItem(key: string) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      kitItems: currentDraft.kitItems.filter((item) => item.key !== key),
    }));
  }

  function createEmptyDraft(): ProductDraft {
    const defaultCategory = catalogs.categories[0];
    const defaultBrand = catalogs.brands[0];
    const defaultUnit = catalogs.units[0];
    const defaultSupplier = catalogs.suppliers[0];
    const defaultBranch =
      catalogs.branches.find((branch) => Number(branch.id) === user?.branch?.id) ||
      catalogs.branches[0];
    const defaultWarehouse =
      catalogs.warehouses.find(
        (warehouse) =>
          defaultBranch &&
          Number(warehouse.branch_id || 0) === Number(defaultBranch.id),
      ) || catalogs.warehouses[0];
    const branchFromWarehouse = defaultWarehouse
      ? catalogs.branches.find(
          (branch) =>
            Number(branch.id) === Number(defaultWarehouse.branch_id || 0),
        )
      : defaultBranch;

    return {
      ...emptyDraft,
      companyId: user?.company?.id ? String(user.company.id) : '',
      company: user?.company?.name || '',
      supplierId: defaultSupplier?.id ? String(defaultSupplier.id) : '',
      supplier: defaultSupplier?.name || '',
      categoryId: defaultCategory?.id ? String(defaultCategory.id) : '',
      category: defaultCategory?.name || 'General',
      brandId: defaultBrand?.id ? String(defaultBrand.id) : '',
      brand: defaultBrand?.name || 'Generica',
      unitId: defaultUnit?.id ? String(defaultUnit.id) : '',
      unit: formatOptionName(defaultUnit) || 'UND',
      purchaseUnitId: defaultUnit?.id ? String(defaultUnit.id) : '',
      purchaseUnit: formatOptionName(defaultUnit) || 'UND',
      saleUnitId: defaultUnit?.id ? String(defaultUnit.id) : '',
      saleUnit: formatOptionName(defaultUnit) || 'UND',
      branchId: branchFromWarehouse?.id ? String(branchFromWarehouse.id) : '',
      branch: branchFromWarehouse?.name || '',
      warehouseId: defaultWarehouse?.id ? String(defaultWarehouse.id) : '',
      warehouse: defaultWarehouse?.name || '',
    };
  }

  function handleCategoryChange(value: string) {
    const category = catalogs.categories.find(
      (option) => String(option.id) === value,
    );

    setDraft((currentDraft) => ({
      ...currentDraft,
      categoryId: value,
      category: category?.name || 'General',
      subcategoryId: '',
      subcategory: '',
    }));
  }

  function handleSubcategoryChange(value: string) {
    const subcategory = catalogs.subcategories.find(
      (option) => String(option.id) === value,
    );

    setDraft((currentDraft) => ({
      ...currentDraft,
      subcategoryId: value,
      subcategory: subcategory?.name || '',
    }));
  }

  function handleBrandChange(value: string) {
    const brand = catalogs.brands.find((option) => String(option.id) === value);

    setDraft((currentDraft) => ({
      ...currentDraft,
      brandId: value,
      brand: brand?.name || 'Generica',
    }));
  }

  function handlePurchaseUnitChange(value: string) {
    const unit = catalogs.units.find((option) => String(option.id) === value);

    setDraft((currentDraft) => ({
      ...currentDraft,
      purchaseUnitId: value,
      purchaseUnit: formatOptionName(unit) || 'UND',
    }));
  }

  function handleSaleUnitChange(value: string) {
    const unit = catalogs.units.find((option) => String(option.id) === value);

    setDraft((currentDraft) => ({
      ...currentDraft,
      saleUnitId: value,
      saleUnit: formatOptionName(unit) || 'UND',
      unitId: value,
      unit: formatOptionName(unit) || 'UND',
    }));
  }

  function handleSupplierChange(value: string) {
    const supplier = catalogs.suppliers.find(
      (option) => String(option.id) === value,
    );

    setDraft((currentDraft) => ({
      ...currentDraft,
      supplierId: value,
      supplier: supplier?.name || '',
    }));
  }

  function handleBranchChange(value: string) {
    const branch = catalogs.branches.find((option) => String(option.id) === value);
    const warehouse = catalogs.warehouses.find(
      (option) => Number(option.branch_id || 0) === Number(value || 0),
    );

    setDraft((currentDraft) => ({
      ...currentDraft,
      branchId: value,
      branch: branch?.name || '',
      warehouseId: warehouse?.id ? String(warehouse.id) : '',
      warehouse: warehouse?.name || '',
    }));
  }

  function handleWarehouseChange(value: string) {
    const warehouse = catalogs.warehouses.find(
      (option) => String(option.id) === value,
    );
    const branch = warehouse?.branch_id
      ? catalogs.branches.find(
          (option) => Number(option.id) === Number(warehouse.branch_id),
        )
      : null;

    setDraft((currentDraft) => ({
      ...currentDraft,
      warehouseId: value,
      warehouse: warehouse?.name || '',
      branchId: branch?.id ? String(branch.id) : currentDraft.branchId,
      branch: branch?.name || currentDraft.branch,
    }));
  }

  function openCreateDialog() {
    setDraft(createEmptyDraft());
    revokeStagedImages(stagedImages);
    setStagedImages([]);
    setProductImages([]);
    setFormError('');
    setSelectedProductId(null);
    setDialogMode('create');
  }

  function upsertProduct(product: ProductRecord) {
    setProducts((currentProducts) => {
      const exists = currentProducts.some((item) => item.id === product.id);

      if (!exists) {
        return [...currentProducts, product];
      }

      return currentProducts.map((item) =>
        item.id === product.id ? product : item,
      );
    });
  }

  async function loadProduct(productId: number) {
    if (!token) {
      throw new ApiError(401, 'No autenticado.');
    }

    const response = await apiRequest<ProductShowResponse>(
      `/products/${productId}`,
      {},
      token,
    );
    const apiProduct = response.product || response.data;

    if (!apiProduct) {
      throw new ApiError(500, 'La API no devolvio el producto solicitado.');
    }

    const product = mapProduct(apiProduct);
    upsertProduct(product);

    return product;
  }

  async function openEditDialog(product: ProductRecord) {
    setActionProductId(product.id);
    setFormError('');
    setPageError('');

    try {
      const freshProduct = await loadProduct(product.id);
      setDraft(toDraft(freshProduct));
      setProductImages(freshProduct.images);
      revokeStagedImages(stagedImages);
      setStagedImages([]);
      setSelectedProductId(freshProduct.id);
      setDialogMode('edit');
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setActionProductId(null);
    }
  }

  async function loadKardex(productId: number) {
    if (!token) {
      return;
    }

    setKardexRows([]);
    setKardexError('');
    setKardexLoading(true);

    try {
      const response = await apiRequest<KardexResponse>(
        `/products/${productId}/movements`,
        {},
        token,
      );
      setKardexRows(
        response.data.map((row) => ({
          id: Number(row.id),
          date: row.date || '',
          detail: row.detail || 'Movimiento',
          input: readNumber(row.input),
          output: readNumber(row.output),
          balance: readNumber(row.balance),
          warehouse: row.warehouse || 'Bodega',
        })),
      );
    } catch (error) {
      setKardexError(getErrorMessage(error));
    } finally {
      setKardexLoading(false);
    }
  }

  async function openProductDialog(
    product: ProductRecord,
    mode: 'kardex',
  ) {
    setActionProductId(product.id);
    setPageError('');

    try {
      const freshProduct = await loadProduct(product.id);
      setProducts((currentProducts) =>
        currentProducts.map((item) => (item.id === freshProduct.id ? freshProduct : item)),
      );
      setSelectedProductId(freshProduct.id);
      setDialogMode(mode);

      if (mode === 'kardex') {
        void loadKardex(freshProduct.id);
      }
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setActionProductId(null);
    }
  }

  function closeDialog() {
    setDialogMode(null);
    setSelectedProductId(null);
    setFormError('');
    setKardexRows([]);
    setKardexError('');
    setProductImages([]);
    revokeStagedImages(stagedImages);
    setStagedImages([]);
  }

  function handleProductImagesChange(images: ProductImageMeta[]) {
    setProductImages(images);

    if (!selectedProductId) return;

    const primary = images.find((image) => image.is_primary) || images[0] || null;

    setProducts((currentProducts) =>
      currentProducts.map((item) =>
        item.id === selectedProductId
          ? { ...item, images, imageUrl: primary ? primary.url : null }
          : item,
      ),
    );
  }

  function productPayload(productDraft: ProductDraft) {
    const categoryId =
      Number(productDraft.categoryId || findOptionId(catalogs.categories, productDraft.category)) ||
      null;
    const subcategoryId = Number(productDraft.subcategoryId) || null;
    const brandId =
      Number(productDraft.brandId || findOptionId(catalogs.brands, productDraft.brand)) ||
      null;
    const supplierId =
      Number(productDraft.supplierId || findOptionId(catalogs.suppliers, productDraft.supplier)) ||
      Number(catalogs.suppliers[0]?.id || 0) ||
      null;
    const purchaseUnitId =
      Number(
        productDraft.purchaseUnitId ||
          productDraft.unitId ||
          findOptionId(catalogs.units, productDraft.purchaseUnit || productDraft.unit),
      ) ||
      Number(catalogs.units[0]?.id || 0) ||
      null;
    const saleUnitId =
      Number(
        productDraft.saleUnitId ||
          productDraft.unitId ||
          findOptionId(catalogs.units, productDraft.saleUnit || productDraft.unit),
      ) ||
      Number(catalogs.units[0]?.id || 0) ||
      null;
    const branchId = Number(productDraft.branchId) || null;
    // Alta en varias sucursales a la vez: solo aplica al crear (no al
    // editar) y solo si el usuario tiene el permiso. warehouse_id sigue
    // mandandose siempre (el backend lo exige), como respaldo con el
    // primero de los seleccionados — pero si warehouse_ids llega con el
    // permiso puesto, el backend lo prioriza y usa todos.
    const useMultiBranch =
      dialogMode === 'create' && canManageMultipleBranches && productDraft.warehouseIds.length > 0;
    const warehouseId = useMultiBranch
      ? Number(productDraft.warehouseIds[0])
      : Number(productDraft.warehouseId) || Number(catalogs.warehouses[0]?.id || 0) || null;

    return {
      company_id: Number(productDraft.companyId) || null,
      name: productDraft.name.trim().toUpperCase(),
      full_name: productDraft.fullName.trim(),
      code: productDraft.code.trim(),
      barcode: productDraft.barcode.trim(),
      supplier_id: supplierId,
      branch_id: branchId,
      warehouse_id: warehouseId,
      warehouse_ids: useMultiBranch ? productDraft.warehouseIds.map((id) => Number(id)) : undefined,
      category_id: categoryId,
      subcategory_id: subcategoryId,
      brand_id: brandId,
      purchase_unit_id: purchaseUnitId,
      sale_unit_id: saleUnitId,
      conversion_factor: parseNumber(productDraft.conversionFactor),
      sale_price: parseNumber(productDraft.salePrice),
      tax_type: productDraft.taxType,
      tax_percentage:
        productDraft.taxType === 'TAXABLE'
          ? parseNumber(productDraft.taxPercentage)
          : 0,
      max_discount_type: productDraft.maxDiscountType || null,
      max_discount_value:
        productDraft.maxDiscountType && productDraft.maxDiscountValue.trim() !== ''
          ? parseNumber(productDraft.maxDiscountValue)
          : null,
      cost: parseNumber(productDraft.cost),
      initial_stock: parseNumber(productDraft.stock),
      stock: parseNumber(productDraft.stock),
      inventory_status: productDraft.inventoryStatus,
      minimum_stock: parseNumber(productDraft.minStock),
      maximum_stock:
        productDraft.maxStock.trim() === '' ? null : parseNumber(productDraft.maxStock),
      allow_negative_stock: productDraft.allowNegativeStock,
      manages_lots: productDraft.managesLots,
      lot_number: productDraft.managesLots ? productDraft.lotNumber.trim() : null,
      manufacturing_date:
        productDraft.managesLots && productDraft.manufacturingDate ? productDraft.manufacturingDate : null,
      expiration_date: productDraft.managesLots && productDraft.expirationDate ? productDraft.expirationDate : null,
      expiration_alert_days:
        productDraft.managesLots && productDraft.expirationAlertDays.trim() !== ''
          ? parseNumber(productDraft.expirationAlertDays)
          : null,
      lot_purchase_price:
        productDraft.managesLots && productDraft.lotPurchasePrice.trim() !== ''
          ? parseNumber(productDraft.lotPurchasePrice)
          : null,
      status: productDraft.status,
      description: productDraft.description.trim(),
      model: productDraft.model.trim(),
      measurement: productDraft.measurement.trim() || null,
      physical_location: productDraft.physicalLocation.trim(),
      is_inventory: productDraft.isInventory,
      is_kit: productDraft.isKit,
      allow_purchase: productDraft.allowPurchase,
      notes: productDraft.notes.trim(),
      observations: productDraft.observations.trim(),
      has_warranty: productDraft.hasWarranty,
      warranty_duration: productDraft.hasWarranty ? parseNumber(productDraft.warrantyDuration) : null,
      warranty_period_unit: productDraft.warrantyPeriodUnit,
      warranty_type: productDraft.hasWarranty ? productDraft.warrantyType.trim() || null : null,
      price_tiers: productDraft.priceTiers
        .filter((tier) => tier.priceListName.trim() !== '' && tier.price.trim() !== '')
        .map((tier) => ({
          price_list_name: tier.priceListName.trim(),
          min_quantity: parseNumber(tier.minQuantity) || 1,
          price: parseNumber(tier.price),
        })),
      kit_items: productDraft.isKit
        ? productDraft.kitItems
            .filter((item) => item.productId.trim() !== '')
            .map((item) => ({
              product_id: Number(item.productId),
              quantity: parseNumber(item.quantity),
            }))
        : [],
    };
  }

  async function persistProduct(productDraft: ProductDraft, productId?: number) {
    if (!token) {
      throw new ApiError(401, 'No autenticado.');
    }

    const response = await apiRequest<ProductMutationResponse>(
      productId ? `/products/${productId}` : '/products',
      {
        method: productId ? 'PUT' : 'POST',
        body: JSON.stringify(productPayload(productDraft)),
      },
      token,
    );

    return mapProduct(response.product);
  }

  async function uploadStagedImages(productId: number, staged: StagedImage[]): Promise<string> {
    if (!token || staged.length === 0) return '';

    // Se suben una por una (no en paralelo) para respetar el orden elegido:
    // la primera que llega al backend queda marcada como principal.
    const failures: string[] = [];

    for (const item of staged) {
      try {
        const formData = new FormData();
        formData.append('image', item.file);
        await apiRequest(`/products/${productId}/images`, { method: 'POST', body: formData }, token);
      } catch (err) {
        failures.push(getErrorMessage(err));
      }
    }

    revokeStagedImages(staged);

    return failures.length > 0
      ? ` Se guardo el producto, pero ${failures.length} imagen(es) no se pudieron subir: ${failures[0]}`
      : '';
  }

  async function saveProduct(createAnother = false) {
    const firstError = Object.values(draftErrors)[0];

    if (firstError) {
      setFormError(firstError);
      return;
    }

    setSubmitting(true);
    setFormError('');

    try {
      const isCreate = dialogMode !== 'edit';
      const savedProduct = await persistProduct(
        draft,
        dialogMode === 'edit' ? selectedProductId || undefined : undefined,
      );

      let imageWarning = '';
      if (isCreate && stagedImages.length > 0) {
        imageWarning = await uploadStagedImages(savedProduct.id, stagedImages);
        setStagedImages([]);
      }

      await loadProducts();
      await loadCatalogs();
      setNotice(
        (dialogMode === 'edit'
          ? 'Producto actualizado en la base de datos.'
          : 'Producto creado en la base de datos.') + imageWarning,
      );

      if (createAnother && dialogMode === 'create') {
        setDraft(createEmptyDraft());
      } else {
        closeDialog();
      }
    } catch (error) {
      setFormError(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    await saveProduct(false);
  }

  async function handleDelete(product: ProductRecord) {
    if (!token) {
      return;
    }

    const accepted = window.confirm(
      `Eliminar ${product.name}? Esta accion se aplicara en la base de datos.`,
    );

    if (!accepted) {
      return;
    }

    try {
      const response = await apiRequest<ProductDeleteResponse>(
        `/products/${product.id}`,
        { method: 'DELETE' },
        token,
      );
      setNotice(response.message);
      await loadProducts();
    } catch (error) {
      setPageError(getErrorMessage(error));
    }
  }

  function handleClearFilters() {
    setSearchTerm('');
    setStatusFilter('Todos');
    setCategoryFilter('Todas');
    setBrandFilter('Todas');
    setUnitFilter('Todas');
    setSupplierFilter('Todos');
    setWarehouseFilter('Todos');
    setCurrentPage(1);
  }

  function handleExport() {
    const headers = [
      'nombre',
      'codigo',
      'proveedor',
      'empresa',
      'sucursal',
      'almacen',
      'categoria',
      'subcategoria',
      'marca',
      'unidad',
      'precio_venta',
      'costo',
      'stock',
      'stock_minimo',
      'estado',
      'descripcion',
      'imagen',
    ];
    const rows = filteredProducts.map((product) => [
      product.name,
      product.code,
      product.supplier,
      product.company,
      product.branch,
      product.warehouse,
      product.category,
      product.subcategory,
      product.brand,
      product.unit,
      product.salePrice,
      product.cost,
      product.stock,
      product.minStock,
      product.status,
      product.description,
      product.imageUrl || '',
    ]);
    const csv = [headers, ...rows]
      .map((row) => row.map(escapeCsv).join(','))
      .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = 'productos.csv';
    anchor.click();
    URL.revokeObjectURL(url);
  }

  async function importProducts(text: string) {
    const importedProducts = parseProductCsv(text);

    if (importedProducts.length === 0) {
      window.alert('No se encontraron productos validos en el archivo.');
      return;
    }

    setImporting(true);
    setPageError('');

    try {
      const productsByCode = new Map(
        products.map((product) => [product.code.toLowerCase(), product.id]),
      );

      for (const productDraft of importedProducts) {
        const code = productDraft.code.toLowerCase();
        const savedProduct = await persistProduct(productDraft, productsByCode.get(code));
        productsByCode.set(savedProduct.code.toLowerCase(), savedProduct.id);
      }

      await loadProducts();
      await loadCatalogs();
      setNotice(`${importedProducts.length} productos importados a la base.`);
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setImporting(false);
    }
  }

  function handleImportFile(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      void importProducts(String(reader.result || ''));
    };
    reader.readAsText(file);
  }

  const statCards = [
    {
      label: 'Productos',
      value: loadingProducts ? '...' : products.length.toString(),
      caption: `${activeCount} activos`,
      icon: <FiBox className="h-7 w-7 text-[#5B3DF5]" />,
      iconClass: 'bg-[#EEEAFE]',
    },
    {
      label: 'Stock bajo',
      value: loadingProducts ? '...' : lowStockCount.toString(),
      caption: 'Requieren reposicion',
      icon: <FiPackage className="h-7 w-7 text-[#F59E0B]" />,
      iconClass: 'bg-[#FFF4DF]',
    },
    {
      label: 'Valor inventario',
      value: loadingProducts ? '...' : formatCurrency(inventoryValue),
      caption: 'Segun costo actual',
      icon: <FiDollarSign className="h-7 w-7 text-[#0F9F37]" />,
      iconClass: 'bg-[#E7F8ED]',
    },
    {
      label: 'Margen promedio',
      value: loadingProducts ? '...' : `${Math.round(averageMargin)}%`,
      caption: 'Sobre precio de venta',
      icon: <FiTrendingUp className="h-7 w-7 text-[#2563EB]" />,
      iconClass: 'bg-[#EAF2FF]',
    },
  ];

  return (
    <div className="space-y-5">
      <h1 className="text-title-md2 font-black uppercase tracking-normal text-black dark:text-white">
        Productos
      </h1>

      {pageError && (
        <div className="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-600 sm:flex-row sm:items-center sm:justify-between">
          <span className="inline-flex items-center gap-2">
            <FiAlertCircle className="h-5 w-5" />
            {pageError}
          </span>
          <button
            type="button"
            onClick={() => void loadProducts()}
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-red-500 px-4 text-white"
          >
            <FiRefreshCw className="h-4 w-4" />
            Reintentar
          </button>
        </div>
      )}

      {notice && (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-[#BFE9CC] bg-[#EFFAF3] px-5 py-3 text-sm font-semibold text-[#0F8D34]">
          <span>{notice}</span>
          <button
            type="button"
            onClick={() => setNotice('')}
            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#0F8D34] hover:bg-white"
          >
            <FiX className="h-4 w-4" />
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        {statCards.map((card) => (
          <div
            key={card.label}
            className="rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="flex items-center gap-5">
              <span
                className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-lg ${card.iconClass}`}
              >
                {card.icon}
              </span>
              <div className="min-w-0">
                <p className="text-base font-semibold text-black dark:text-white">
                  {card.label}
                </p>
                <p className="mt-2 truncate text-title-sm font-black text-black dark:text-white">
                  {card.value}
                </p>
                <p className="mt-2 text-sm font-medium text-slate-500 dark:text-bodydark">
                  {card.caption}
                </p>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark md:p-7">
        <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div className="flex w-full items-center gap-3 xl:max-w-lg">
            <div className="relative w-full">
              <FiSearch className="absolute left-5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
              <input
                type="search"
                value={searchTerm}
                onChange={(event) => {
                  setSearchTerm(event.target.value);
                  setCurrentPage(1);
                }}
                placeholder="Buscar productos..."
                className="h-13 w-full rounded-lg border border-stroke bg-white pl-14 pr-4 text-base text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
              />
            </div>
            <button
              type="button"
              onClick={() => setFiltersOpen(true)}
              title="Filtros"
              className="relative inline-flex h-13 w-13 shrink-0 items-center justify-center rounded-lg border border-stroke bg-white text-slate-600 transition hover:border-primary hover:text-primary dark:border-strokedark dark:bg-boxdark dark:text-bodydark"
            >
              <FiFilter className="h-5 w-5" />
              {activeFilterCount > 0 && (
                <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] font-black text-white">
                  {activeFilterCount}
                </span>
              )}
            </button>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              disabled={loadingProducts || importing}
              className="inline-flex h-13 items-center justify-center gap-3 rounded-lg border border-[#16A34A] bg-white px-5 text-base font-bold text-[#0E8A2B] transition hover:bg-[#ECFDF3] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-boxdark"
            >
              <FiUpload className="h-5 w-5" />
              {importing ? 'Importando' : 'Importar'}
            </button>
            <button
              type="button"
              onClick={handleExport}
              disabled={loadingProducts}
              className="inline-flex h-13 items-center justify-center gap-3 rounded-lg border border-[#16A34A] bg-white px-5 text-base font-bold text-[#0E8A2B] transition hover:bg-[#ECFDF3] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-boxdark"
            >
              <FiDownload className="h-5 w-5" />
              Exportar
            </button>
            <button
              type="button"
              onClick={openCreateDialog}
              disabled={loadingProducts}
              className="inline-flex h-13 items-center justify-center gap-3 rounded-lg bg-primary px-5 text-base font-bold text-white shadow-4 transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <FiFilePlus className="h-5 w-5" />
              Nuevo
              <FiPlus className="h-5 w-5" />
            </button>
          </div>

          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,text/csv"
            onChange={handleImportFile}
            className="hidden"
          />
        </div>

        {activeFilterCount > 0 && (
          <div className="mt-5 flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-slate-500">Filtros activos:</span>
            {statusFilter !== 'Todos' && (
              <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Estado: {statusFilter}</span>
            )}
            {categoryFilter !== 'Todas' && (
              <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Categoria: {categoryFilter}</span>
            )}
            {brandFilter !== 'Todas' && (
              <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Marca: {brandFilter}</span>
            )}
            {unitFilter !== 'Todas' && (
              <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">Unidad: {unitFilter}</span>
            )}
            <button type="button" onClick={handleClearFilters} className="text-xs font-semibold text-red-500 hover:underline">
              Limpiar todo
            </button>
          </div>
        )}

        {filtersOpen && (
          <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
            <div className="w-full max-w-2xl rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
              <div className="mb-6 flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                  <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <FiFilter className="h-5 w-5" />
                  </span>
                  <div>
                    <p className="text-xl font-black text-black dark:text-white">Filtros</p>
                    <p className="text-sm text-slate-500">Filtra la lista de productos por estado, categoria, marca o unidad.</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setFiltersOpen(false)}
                  className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white"
                >
                  <FiX className="h-5 w-5" />
                </button>
              </div>

              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <Field label="Estado">
                  <SelectWrap>
                    <select
                      value={statusFilter}
                      onChange={(event) => {
                        setStatusFilter(event.target.value);
                        setCurrentPage(1);
                      }}
                      className={selectClass}
                    >
                      <option>Todos</option>
                      <option>Activo</option>
                      <option>Inactivo</option>
                    </select>
                  </SelectWrap>
                </Field>

                <Field label="Categoria">
                  <SelectWrap>
                    <select
                      value={categoryFilter}
                      onChange={(event) => {
                        setCategoryFilter(event.target.value);
                        setCurrentPage(1);
                      }}
                      className={selectClass}
                    >
                      <option>Todas</option>
                      {categories.map((category) => (
                        <option key={category}>{category}</option>
                      ))}
                    </select>
                  </SelectWrap>
                </Field>

                <Field label="Marca">
                  <SelectWrap>
                    <select
                      value={brandFilter}
                      onChange={(event) => {
                        setBrandFilter(event.target.value);
                        setCurrentPage(1);
                      }}
                      className={selectClass}
                    >
                      <option>Todas</option>
                      {brands.map((brand) => (
                        <option key={brand}>{brand}</option>
                      ))}
                    </select>
                  </SelectWrap>
                </Field>

                <Field label="Unidad">
                  <SelectWrap>
                    <select
                      value={unitFilter}
                      onChange={(event) => {
                        setUnitFilter(event.target.value);
                        setCurrentPage(1);
                      }}
                      className={selectClass}
                    >
                      <option>Todas</option>
                      {units.map((unit) => (
                        <option key={unit}>{unit}</option>
                      ))}
                    </select>
                  </SelectWrap>
                </Field>
              </div>

              <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <button
                  type="button"
                  onClick={handleClearFilters}
                  className="inline-flex h-12 items-center justify-center gap-3 rounded-lg border border-stroke px-5 text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
                >
                  <FiRefreshCw className="h-5 w-5" />
                  Limpiar filtros
                </button>
                <button
                  type="button"
                  onClick={() => setFiltersOpen(false)}
                  className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white transition hover:bg-opacity-90"
                >
                  Aplicar y cerrar
                </button>
              </div>
            </div>
          </div>
        )}

        <div className="mt-7 overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
          <table className="w-full min-w-[1120px] table-auto">
            <thead>
              <tr className="bg-gray-50 text-left dark:bg-meta-4">
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Producto
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Nombre
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Codigo
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Marca
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Unidad
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Precio venta
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Stock
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Estado
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Descripcion
                </th>
                <th className="px-4 py-4 text-center text-xs font-black uppercase text-black dark:text-white">
                  Opciones
                </th>
              </tr>
            </thead>
            <tbody>
              {loadingProducts && (
                <tr>
                  <td
                    colSpan={10}
                    className="px-4 py-14 text-center text-sm font-semibold text-slate-500"
                  >
                    Cargando productos desde la base de datos...
                  </td>
                </tr>
              )}

              {!loadingProducts &&
                paginatedProducts.map((product) => (
                  <tr
                    key={product.id}
                    className="border-t border-stroke bg-white dark:border-strokedark dark:bg-boxdark"
                    >
                      <td className="px-4 py-5">
                      <ProductPicture
                        product={product}
                        onOpen={() => setImagePreviewProduct(product)}
                      />
                    </td>
                    <td className="px-4 py-5">
                      <p className="text-base font-black text-black dark:text-white">
                        {product.name}
                      </p>
                      <span className="mt-3 inline-flex items-center gap-2 rounded-md bg-[#E8F0FF] px-3 py-1 text-xs font-bold uppercase text-primary">
                        <FiPackage className="h-4 w-4" />
                        {product.subcategory
                          ? `${product.category} / ${product.subcategory}`
                          : product.category}
                      </span>
                    </td>
                    <td className="px-4 py-5 text-sm font-black text-red-500">
                      {product.code || 'S/C'}
                    </td>
                    <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                      {product.brand}
                    </td>
                    <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                      {product.unit}
                    </td>
                    <td className="px-4 py-5 text-sm font-black text-[#03A323]">
                      {formatCurrency(product.salePrice)}
                    </td>
                    <td className="px-4 py-5 text-center text-sm font-black text-black dark:text-white">
                      {formatQuantity(product.stock)}
                    </td>
                    <td className="px-4 py-5">
                      <SwitchField
                        checked={product.status === 'Activo'}
                        onChange={() => void handleToggleStatus(product)}
                        label="Estado"
                      />
                    </td>
                    <td className="max-w-60 px-4 py-5 text-sm font-semibold text-black dark:text-white">
                      {product.description || product.model || 'Sin descripcion'}
                    </td>
                    <td className="px-4 py-5">
                      <div className="flex justify-center">
                        <ActionsMenu
                          ariaLabel={`Mas opciones de ${product.name}`}
                          disabled={actionProductId === product.id}
                          items={[
                            {
                              label: 'Ver detalle',
                              icon: FiEye,
                              // Se abre en una pestana nueva, ocupando toda
                              // la pantalla (ruta completa, no un modal),
                              // para poder ver el carrusel de imagenes y la
                              // disponibilidad por sucursal con mas espacio.
                              onClick: () =>
                                window.open(`/products/detail/${product.id}`, '_blank', 'noopener,noreferrer'),
                            },
                            {
                              label: 'Editar',
                              icon: FiEdit2,
                              onClick: () => void openEditDialog(product),
                            },
                            {
                              label: 'Cardex',
                              icon: FiBarChart2,
                              onClick: () => void openProductDialog(product, 'kardex'),
                            },
                            {
                              label: 'Eliminar',
                              icon: FiTrash2,
                              variant: 'danger',
                              onClick: () => void handleDelete(product),
                            },
                          ]}
                        />
                      </div>
                    </td>
                  </tr>
                ))}

              {!loadingProducts && paginatedProducts.length === 0 && (
                <tr>
                  <td
                    colSpan={10}
                    className="px-4 py-14 text-center text-sm font-semibold text-slate-500"
                  >
                    No hay productos que coincidan con los filtros.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="mt-5 flex flex-col gap-4 rounded-lg border border-stroke px-4 py-4 dark:border-strokedark sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm font-semibold text-black dark:text-white">
            Mostrando {firstItem} a {lastItem} de {filteredProducts.length}{' '}
            productos
          </p>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setCurrentPage(1)}
              disabled={currentPage === 1}
              className="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-strokedark dark:text-white"
            >
              <FiChevronsLeft className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={() => setCurrentPage((page) => Math.max(page - 1, 1))}
              disabled={currentPage === 1}
              className="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-strokedark dark:text-white"
            >
              <FiChevronLeft className="h-5 w-5" />
            </button>
            <span className="inline-flex h-11 min-w-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-white">
              {currentPage}
            </span>
            <button
              type="button"
              onClick={() =>
                setCurrentPage((page) => Math.min(page + 1, totalPages))
              }
              disabled={currentPage === totalPages}
              className="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-strokedark dark:text-white"
            >
              <FiChevronRight className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={() => setCurrentPage(totalPages)}
              disabled={currentPage === totalPages}
              className="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-strokedark dark:text-white"
            >
              <FiChevronsRight className="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>

      {imagePreviewProduct && (
        <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/70 px-4 py-6">
          <div className="max-h-full w-full max-w-5xl overflow-hidden rounded-lg border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center justify-between gap-4 border-b border-stroke px-5 py-4 dark:border-strokedark">
              <div className="min-w-0">
                <p className="truncate text-lg font-black text-black dark:text-white">
                  {imagePreviewProduct.name}
                </p>
                <p className="mt-1 text-sm font-semibold text-primary">
                  {imagePreviewProduct.code || 'Sin codigo'}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setImagePreviewProduct(null)}
                className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>
            <div className="flex max-h-[78vh] items-center justify-center bg-[#F6F8FC] p-4 dark:bg-black/20">
              <img
                src={imagePreviewProduct.imageUrl || ''}
                alt={imagePreviewProduct.name}
                className="max-h-[72vh] w-full object-contain"
              />
            </div>
          </div>
        </div>
      )}

      {(dialogMode === 'create' || dialogMode === 'edit') && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={handleSubmit}
            className="max-h-full w-full max-w-6xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">
                  {dialogMode === 'edit' ? 'Editar producto' : 'Nuevo producto'}
                </p>
              </div>
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && (
              <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">
                {formError}
              </div>
            )}

            <div className="space-y-5">
              <FormCard title="Informacion general">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                  <Field label="Codigo interno" required error={draftErrors.code}>
                    <input
                      value={draft.code}
                      onChange={(event) => updateDraft('code', event.target.value)}
                      className={inputClass}
                      placeholder="785411466"
                    />
                  </Field>
                  <Field label="Codigo de barras" error={draftErrors.barcode}>
                    <input
                      value={draft.barcode}
                      onChange={(event) =>
                        updateDraft('barcode', event.target.value)
                      }
                      className={inputClass}
                      placeholder="7501234567890"
                    />
                  </Field>
                  <Field label="Nombre corto" required error={draftErrors.name}>
                    <input
                      value={draft.name}
                      onChange={(event) => updateDraft('name', event.target.value)}
                      className={inputClass}
                      placeholder="ALTERNADOR"
                    />
                  </Field>
                  <Field label="Nombre completo">
                    <input
                      value={draft.fullName}
                      onChange={(event) =>
                        updateDraft('fullName', event.target.value)
                      }
                      className={inputClass}
                      placeholder="ALTERNADOR TOYOTA HILUX 2.8"
                    />
                  </Field>
                  <Field label="Modelo">
                    <input
                      value={draft.model}
                      onChange={(event) => updateDraft('model', event.target.value)}
                      className={inputClass}
                      placeholder="HILUX 2016-2024"
                    />
                  </Field>
                  <Field label="Medida">
                    <input
                      value={draft.measurement}
                      onChange={(event) => updateDraft('measurement', event.target.value)}
                      className={inputClass}
                      placeholder='Formato libre, ej. 30x34x12 o "Altura: 30cm, Largo: 66cm, Grosor: 5cm"'
                    />
                  </Field>
                  <Field
                    label="Categoria"
                    required
                    error={draftErrors.categoryId}
                  >
                    <SelectWrap>
                      <select
                        value={draft.categoryId}
                        onChange={(event) =>
                          handleCategoryChange(event.target.value)
                        }
                        className={selectClass}
                        disabled={loadingCatalogs}
                      >
                        <option value="" disabled hidden>Selecciona categoria</option>
                        {catalogs.categories.map((category) => (
                          <option key={category.id} value={category.id}>
                            {category.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field label="Subcategoria">
                    <SelectWrap>
                      <select
                        value={draft.subcategoryId}
                        onChange={(event) =>
                          handleSubcategoryChange(event.target.value)
                        }
                        className={selectClass}
                        disabled={loadingCatalogs || !draft.categoryId}
                      >
                        <option value="">Sin subcategoria</option>
                        {availableSubcategories.map((subcategory) => (
                          <option key={subcategory.id} value={subcategory.id}>
                            {subcategory.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field label="Marca" required error={draftErrors.brandId}>
                    <SelectWrap>
                      <select
                        value={draft.brandId}
                        onChange={(event) => handleBrandChange(event.target.value)}
                        className={selectClass}
                        disabled={loadingCatalogs}
                      >
                        <option value="" disabled hidden>Selecciona marca</option>
                        {catalogs.brands.map((brand) => (
                          <option key={brand.id} value={brand.id}>
                            {brand.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field
                    label="Proveedor principal"
                    required
                    error={draftErrors.supplierId}
                  >
                    <SelectWrap>
                      <select
                        value={draft.supplierId}
                        onChange={(event) =>
                          handleSupplierChange(event.target.value)
                        }
                        className={selectClass}
                        disabled={loadingCatalogs}
                      >
                        <option value="" disabled hidden>Selecciona proveedor</option>
                        {catalogs.suppliers.map((supplier) => (
                          <option key={supplier.id} value={supplier.id}>
                            {supplier.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field label="Estado">
                    <SwitchField
                      checked={draft.status === 'Activo'}
                      onChange={(checked) =>
                        updateDraft('status', checked ? 'Activo' : 'Inactivo')
                      }
                      label="Estado"
                    />
                  </Field>
                  <div className="md:col-span-2 xl:col-span-3">
                    <Field label="Descripcion">
                      <textarea
                        value={draft.description}
                        onChange={(event) =>
                          updateDraft('description', event.target.value)
                        }
                        className={textareaClass}
                        placeholder="Repuestos para Toyota y Hino"
                      />
                    </Field>
                  </div>
                  <div className="md:col-span-2 xl:col-span-3">
                    <ProductImagesManager
                      productId={dialogMode === 'edit' ? selectedProductId : null}
                      images={productImages}
                      onImagesChange={handleProductImagesChange}
                      stagedImages={stagedImages}
                      onStagedImagesChange={setStagedImages}
                      token={token}
                    />
                  </div>
                </div>
              </FormCard>

              <FormCard title="Unidades">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
                  <Field
                    label="Unidad de compra"
                    required
                    error={draftErrors.purchaseUnitId}
                  >
                    <SelectWrap>
                      <select
                        value={draft.purchaseUnitId}
                        onChange={(event) =>
                          handlePurchaseUnitChange(event.target.value)
                        }
                        className={selectClass}
                        disabled={loadingCatalogs}
                      >
                        <option value="" disabled hidden>Selecciona unidad</option>
                        {catalogs.units.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.short_name
                              ? `${unit.short_name} - ${unit.name}`
                              : unit.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field
                    label="Unidad de venta"
                    required
                    error={draftErrors.saleUnitId}
                  >
                    <SelectWrap>
                      <select
                        value={draft.saleUnitId}
                        onChange={(event) =>
                          handleSaleUnitChange(event.target.value)
                        }
                        className={selectClass}
                        disabled={loadingCatalogs}
                      >
                        <option value="" disabled hidden>Selecciona unidad</option>
                        {catalogs.units.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.short_name
                              ? `${unit.short_name} - ${unit.name}`
                              : unit.name}
                          </option>
                        ))}
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field
                    label="Factor de conversion"
                    required
                    error={draftErrors.conversionFactor}
                  >
                    <input
                      type="number"
                      min="0.000001"
                      step="0.000001"
                      value={draft.conversionFactor}
                      onChange={(event) =>
                        updateDraft('conversionFactor', event.target.value)
                      }
                      className={inputClass}
                    />
                  </Field>
                </div>
                <p className="mt-4 rounded-lg bg-[#E8F0FF] px-4 py-3 text-sm font-bold text-primary">
                  1 {draft.purchaseUnit || 'unidad de compra'} ={' '}
                  {formatQuantity(parseNumber(draft.conversionFactor))}{' '}
                  {draft.saleUnit || 'unidad de venta'}
                </p>
              </FormCard>

              <FormCard title="Precios">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                  <Field label="Costo de compra" required error={draftErrors.cost}>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.cost}
                      onChange={(event) => updateDraft('cost', event.target.value)}
                      className={inputClass}
                      placeholder="200"
                    />
                  </Field>
                  <Field
                    label="Precio sin impuestos"
                    required
                    error={draftErrors.salePrice}
                  >
                    <input
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={draft.salePrice}
                      onChange={(event) =>
                        updateDraft('salePrice', event.target.value)
                      }
                      className={inputClass}
                      placeholder="200"
                    />
                  </Field>
                  <Field label="Tipo de impuesto">
                    <SelectWrap>
                      <select
                        value={draft.taxType}
                        onChange={(event) => {
                          const nextTaxType = normalizeTaxType(event.target.value);
                          setDraft((currentDraft) => ({
                            ...currentDraft,
                            taxType: nextTaxType,
                            taxPercentage:
                              nextTaxType === 'EXEMPT'
                                ? '0'
                                : currentDraft.taxPercentage,
                          }));
                        }}
                        className={selectClass}
                      >
                        <option value="EXEMPT">Exento</option>
                        <option value="TAXABLE">Gravado</option>
                      </select>
                    </SelectWrap>
                  </Field>
                  {draft.taxType === 'TAXABLE' && (
                    <Field
                      label="Porcentaje impuesto"
                      required
                      error={draftErrors.taxPercentage}
                    >
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={draft.taxPercentage}
                        onChange={(event) =>
                          updateDraft('taxPercentage', event.target.value)
                        }
                        className={inputClass}
                        placeholder="15"
                      />
                    </Field>
                  )}
                </div>
                <div className="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                  <Field label="Descuento maximo">
                    <SelectWrap>
                      <select
                        value={draft.maxDiscountType}
                        onChange={(event) => {
                          const nextType = normalizeMaxDiscountType(event.target.value) ?? '';
                          setDraft((currentDraft) => ({
                            ...currentDraft,
                            maxDiscountType: nextType,
                            maxDiscountValue: nextType === '' ? '' : currentDraft.maxDiscountValue,
                          }));
                        }}
                        className={selectClass}
                      >
                        <option value="">Sin limite propio</option>
                        <option value="PERCENTAGE">Porcentaje</option>
                        <option value="FIXED">Monto fijo por unidad</option>
                      </select>
                    </SelectWrap>
                  </Field>
                  {draft.maxDiscountType && (
                    <Field
                      label={draft.maxDiscountType === 'PERCENTAGE' ? 'Descuento maximo (%)' : 'Descuento maximo (C$ por unidad)'}
                      required
                      error={draftErrors.maxDiscountValue}
                    >
                      <input
                        type="number"
                        min="0"
                        max={draft.maxDiscountType === 'PERCENTAGE' ? '100' : undefined}
                        step="0.01"
                        value={draft.maxDiscountValue}
                        onChange={(event) => updateDraft('maxDiscountValue', event.target.value)}
                        className={inputClass}
                        placeholder={draft.maxDiscountType === 'PERCENTAGE' ? '10' : '20'}
                      />
                    </Field>
                  )}
                </div>
                <p className="mt-3 text-xs text-slate-500">
                  Al facturar este producto, ningun descuento por linea podra superar este limite (se valida junto
                  con el tope general de Configuracion General y el limite personal del vendedor: gana el mas
                  restrictivo). Dejalo en "Sin limite propio" para que el producto herede el tope general.
                </p>
                <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                  {[
                    ['Precio Base', formatCurrency(previewSalePrice)],
                    ['IVA', formatCurrency(previewTaxAmount)],
                    ['Precio Final', formatCurrency(previewFinalPrice)],
                  ].map(([label, value]) => (
                    <div
                      key={label}
                      className="rounded-lg border border-stroke px-4 py-3 dark:border-strokedark"
                    >
                      <p className="text-xs font-bold uppercase text-slate-500">
                        {label}
                      </p>
                      <p className="mt-2 text-base font-black text-black dark:text-white">
                        {value}
                      </p>
                    </div>
                  ))}
                </div>
              </FormCard>

              <FormCard title="Precios por Volumen">
                <p className="mb-4 text-sm text-slate-500">
                  Precios adicionales que aplican a partir de cierta cantidad (mayoreo, distribuidor, etc.). Se
                  validan al vender: no se puede vender por debajo del precio que corresponde a la cantidad.
                </p>
                {draftErrors.priceTiers && (
                  <p className="mb-4 text-xs font-semibold text-red-500">{draftErrors.priceTiers}</p>
                )}
                {/* Sugerencias para el input de "Tipo de precio" de abajo:
                    nombres ya usados antes (propios de la empresa), pero el
                    usuario puede escribir cualquier otro nombre nuevo. */}
                <datalist id="price-type-suggestions">
                  {catalogs.priceTypes
                    .filter((priceType) => priceType.is_active)
                    .map((priceType) => (
                      <option key={priceType.id} value={priceType.name} />
                    ))}
                </datalist>
                <div className="space-y-3">
                  {draft.priceTiers.map((tier) => (
                    <div
                      key={tier.key}
                      className="grid grid-cols-1 gap-3 rounded-lg border border-stroke p-3 dark:border-strokedark md:grid-cols-[2fr_1fr_1fr_auto] md:items-end md:border-0 md:p-0"
                    >
                      <Field label="Tipo de precio">
                        {/* Texto libre (no un catalogo cerrado): el usuario
                            lo nombra como quiera. El <datalist> solo
                            sugiere los nombres ya usados antes (propios) —
                            no impide escribir uno nuevo. */}
                        <input
                          list="price-type-suggestions"
                          value={tier.priceListName}
                          onChange={(event) => updatePriceTier(tier.key, { priceListName: event.target.value })}
                          className={inputClass}
                          placeholder="Ej. Mayorista, Distribuidor..."
                        />
                      </Field>
                      <Field label="Cantidad minima">
                        <input
                          type="number"
                          min="1"
                          step="1"
                          value={tier.minQuantity}
                          onChange={(event) => updatePriceTier(tier.key, { minQuantity: event.target.value })}
                          className={inputClass}
                          placeholder="10"
                        />
                      </Field>
                      <Field label="Precio">
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={tier.price}
                          onChange={(event) => updatePriceTier(tier.key, { price: event.target.value })}
                          className={inputClass}
                          placeholder="180"
                        />
                      </Field>
                      <button
                        type="button"
                        onClick={() => removePriceTier(tier.key)}
                        className="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-stroke text-red-500 hover:border-red-500 dark:border-strokedark"
                        aria-label="Quitar precio por volumen"
                      >
                        <FiTrash2 className="h-5 w-5" />
                      </button>
                    </div>
                  ))}
                  {draft.priceTiers.length === 0 && (
                    <p className="rounded-lg border border-dashed border-stroke px-4 py-6 text-center text-sm text-slate-500 dark:border-strokedark">
                      No hay precios por volumen configurados.
                    </p>
                  )}
                </div>
                <button
                  type="button"
                  onClick={addPriceTier}
                  className="mt-4 inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2.5 text-sm font-semibold text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
                >
                  <FiPlus className="h-4 w-4" /> Agregar precio por volumen
                </button>
              </FormCard>

              <FormCard title="Inventario">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                  {dialogMode === 'create' && canManageMultipleBranches ? (
                    <Field
                      label="Sucursales / almacenes"
                      required
                      error={draftErrors.warehouseId}
                      className="md:col-span-2 xl:col-span-2"
                    >
                      <div className="rounded-lg border border-stroke p-3 dark:border-strokedark">
                        <p className="mb-2 text-xs text-slate-500">
                          Marca uno o varios: el stock inicial se registra en cada sucursal elegida, con su propio
                          movimiento de kardex.
                        </p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                          {catalogs.warehouses.map((warehouse) => (
                            <label
                              key={warehouse.id}
                              className="flex items-center gap-2 rounded-lg border border-stroke px-3 py-2 text-sm dark:border-strokedark"
                            >
                              <input
                                type="checkbox"
                                checked={draft.warehouseIds.includes(String(warehouse.id))}
                                onChange={() => toggleWarehouseSelection(String(warehouse.id))}
                                className="h-4 w-4"
                              />
                              <span className="text-black dark:text-white">
                                {warehouse.name}
                                {warehouse.branch_name && (
                                  <span className="text-slate-500"> ({warehouse.branch_name})</span>
                                )}
                              </span>
                            </label>
                          ))}
                        </div>
                      </div>
                    </Field>
                  ) : (
                    <>
                      <Field label="Sucursal">
                        <SelectWrap>
                          <select
                            value={draft.branchId}
                            onChange={(event) => handleBranchChange(event.target.value)}
                            className={selectClass}
                            disabled={loadingCatalogs}
                          >
                            <option value="">Sin sucursal</option>
                            {catalogs.branches.map((branch) => (
                              <option key={branch.id} value={branch.id}>
                                {branch.name}
                              </option>
                            ))}
                          </select>
                        </SelectWrap>
                      </Field>
                      <Field
                        label="Almacen inicial"
                        required
                        error={draftErrors.warehouseId}
                      >
                        <SelectWrap>
                          <select
                            value={draft.warehouseId}
                            onChange={(event) =>
                              handleWarehouseChange(event.target.value)
                            }
                            className={selectClass}
                            disabled={loadingCatalogs}
                          >
                            <option value="" disabled hidden>Selecciona almacen</option>
                            {availableWarehouses.map((warehouse) => (
                              <option key={warehouse.id} value={warehouse.id}>
                                {warehouse.name}
                              </option>
                            ))}
                          </select>
                        </SelectWrap>
                      </Field>
                    </>
                  )}
                  <Field label="Estado inventario">
                    <SelectWrap>
                      <select
                        value={draft.inventoryStatus}
                        onChange={(event) =>
                          updateDraft(
                            'inventoryStatus',
                            event.target.value as InventoryStatus,
                          )
                        }
                        className={selectClass}
                      >
                        <option value="RECEIVED">Recibido</option>
                        <option value="PENDING_RECEIPT">
                          Pendiente por recibir
                        </option>
                        <option value="IN_TRANSIT">En transito</option>
                        <option value="RESERVED">Reservado</option>
                      </select>
                    </SelectWrap>
                  </Field>
                  <Field
                    label={dialogMode === 'edit' ? 'Stock actual' : 'Stock inicial'}
                    error={draftErrors.stock}
                  >
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.stock}
                      onChange={(event) => updateDraft('stock', event.target.value)}
                      className={inputClass}
                    />
                  </Field>
                  {dialogMode === 'edit' && draft.stockByBranch.length > 0 && (
                    <div className="md:col-span-2 xl:col-span-3">
                      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">
                        Disponibilidad por sucursal
                      </span>
                      <div className="overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
                        <table className="min-w-full text-left text-sm">
                          <thead className="bg-slate-50 dark:bg-white/5">
                            <tr className="text-xs font-bold uppercase text-slate-500">
                              <th className="px-3 py-2">Sucursal</th>
                              <th className="px-3 py-2 text-right">Existencia</th>
                              <th className="px-3 py-2 text-right">Disponible</th>
                            </tr>
                          </thead>
                          <tbody>
                            {draft.stockByBranch.map((row) => (
                              <tr key={row.branch_id ?? row.branch_name} className="border-t border-stroke dark:border-strokedark">
                                <td className="px-3 py-2">{row.branch_name}</td>
                                <td className="px-3 py-2 text-right">{row.quantity}</td>
                                <td className="px-3 py-2 text-right font-semibold">{row.available_quantity}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                  <Field
                    label="Alerta existencia minima"
                    required
                    error={draftErrors.minStock}
                  >
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.minStock}
                      onChange={(event) =>
                        updateDraft('minStock', event.target.value)
                      }
                      className={inputClass}
                    />
                  </Field>
                  <Field
                    label="Alerta existencia maxima"
                    error={draftErrors.maxStock}
                  >
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.maxStock}
                      onChange={(event) =>
                        updateDraft('maxStock', event.target.value)
                      }
                      className={inputClass}
                    />
                  </Field>
                  <ToggleField
                    label="Permitir stock negativo"
                    checked={draft.allowNegativeStock}
                    onChange={(checked) =>
                      updateDraft('allowNegativeStock', checked)
                    }
                  />
                  <ToggleField
                    label="Maneja lotes"
                    checked={draft.managesLots}
                    onChange={(checked) => updateDraft('managesLots', checked)}
                  />
                  {draft.managesLots && (
                    <>
                      <Field label="Numero de lote" required error={draftErrors.lotNumber}>
                        <input
                          value={draft.lotNumber}
                          onChange={(event) =>
                            updateDraft('lotNumber', event.target.value)
                          }
                          className={inputClass}
                          placeholder="Ej. L-2026-045"
                        />
                      </Field>
                      <Field label="Fecha de fabricacion">
                        <input
                          type="date"
                          value={draft.manufacturingDate}
                          onChange={(event) =>
                            updateDraft('manufacturingDate', event.target.value)
                          }
                          className={inputClass}
                        />
                      </Field>
                      <Field
                        label="Fecha de vencimiento"
                        error={draftErrors.expirationDate}
                      >
                        <input
                          type="date"
                          value={draft.expirationDate}
                          onChange={(event) =>
                            updateDraft('expirationDate', event.target.value)
                          }
                          className={inputClass}
                        />
                      </Field>
                      <Field
                        label="Alertar antes de vencer (dias)"
                        error={draftErrors.expirationAlertDays}
                      >
                        <input
                          type="number"
                          min="1"
                          step="1"
                          value={draft.expirationAlertDays}
                          onChange={(event) =>
                            updateDraft('expirationAlertDays', event.target.value)
                          }
                          className={inputClass}
                          placeholder="Ej. 30"
                        />
                      </Field>
                      <Field label="Precio de compra del lote">
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          value={draft.lotPurchasePrice}
                          onChange={(event) =>
                            updateDraft('lotPurchasePrice', event.target.value)
                          }
                          className={inputClass}
                          placeholder="Si difiere del costo del producto"
                        />
                      </Field>
                    </>
                  )}
                </div>
              </FormCard>

              <FormCard title="Ubicacion">
                <Field label="Ubicacion fisica">
                  <input
                    value={draft.physicalLocation}
                    onChange={(event) =>
                      updateDraft('physicalLocation', event.target.value)
                    }
                    className={inputClass}
                    placeholder="Pasillo A, Estante 4, Nivel 2"
                  />
                </Field>
              </FormCard>

              <FormCard title="Garantia">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <ToggleField
                    label="Este producto tiene garantia"
                    checked={draft.hasWarranty}
                    onChange={(checked) => updateDraft('hasWarranty', checked)}
                  />
                  {draft.hasWarranty && (
                    <>
                      <Field label="Duracion" required error={draftErrors.warrantyDuration}>
                        <input
                          type="number"
                          min="1"
                          step="1"
                          value={draft.warrantyDuration}
                          onChange={(event) => updateDraft('warrantyDuration', event.target.value)}
                          className={inputClass}
                        />
                      </Field>
                      <Field label="Unidad">
                        <select
                          value={draft.warrantyPeriodUnit}
                          onChange={(event) =>
                            updateDraft('warrantyPeriodUnit', event.target.value as WarrantyPeriodUnit)
                          }
                          className={selectClass}
                        >
                          <option value="DAYS">Dias</option>
                          <option value="MONTHS">Meses</option>
                          <option value="YEARS">Anos</option>
                        </select>
                      </Field>
                      <Field label="Tipo de garantia">
                        <input
                          value={draft.warrantyType}
                          onChange={(event) => updateDraft('warrantyType', event.target.value)}
                          className={inputClass}
                          placeholder="Ej. Garantia de fabrica"
                        />
                      </Field>
                    </>
                  )}
                </div>
                {draft.hasWarranty && (
                  <p className="mt-2 text-xs text-slate-500">
                    Al vender este producto, la garantia empieza a correr desde la fecha de la venta: la factura
                    mostrara hasta cuando queda vigente.
                  </p>
                )}
              </FormCard>

              <FormCard title="Configuraciones">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <ToggleField
                    label="Producto inventariable"
                    checked={draft.isInventory}
                    onChange={(checked) =>
                      setDraft((currentDraft) => ({
                        ...currentDraft,
                        isInventory: checked,
                        isKit: checked ? false : currentDraft.isKit,
                      }))
                    }
                  />
                  <ToggleField
                    label="Combo / Kit"
                    checked={draft.isKit}
                    onChange={(checked) =>
                      setDraft((currentDraft) => ({
                        ...currentDraft,
                        isKit: checked,
                        isInventory: checked ? false : currentDraft.isInventory,
                      }))
                    }
                  />
                  <ToggleField
                    label="Permitir compra"
                    checked={draft.allowPurchase}
                    onChange={(checked) => updateDraft('allowPurchase', checked)}
                  />
                </div>
                <p className="mt-3 text-xs text-slate-500">
                  Si "Producto inventariable" y "Combo / Kit" quedan los dos apagados, el producto se trata como
                  servicio (no lleva existencia propia).
                </p>
              </FormCard>

              {draft.isKit && (
                <FormCard title="Componentes del combo/kit">
                  <p className="mb-4 text-sm text-slate-500">
                    Que otros productos de la empresa componen este combo/kit, y cuantos de cada uno. Al vender
                    este producto se descuenta el inventario de cada componente (no el del kit); al anular la
                    venta, se devuelve.
                  </p>
                  {draftErrors.kitItems && (
                    <p className="mb-4 text-xs font-semibold text-red-500">{draftErrors.kitItems}</p>
                  )}
                  <div className="space-y-3">
                    {draft.kitItems.map((item) => (
                      <div
                        key={item.key}
                        className="grid grid-cols-1 gap-3 rounded-lg border border-stroke p-3 dark:border-strokedark md:grid-cols-[3fr_1fr_auto] md:items-end md:border-0 md:p-0"
                      >
                        <Field label="Producto">
                          <SelectWrap>
                            <select
                              value={item.productId}
                              onChange={(event) => updateKitItem(item.key, { productId: event.target.value })}
                              className={selectClass}
                            >
                              <option value="" disabled hidden>Selecciona un producto</option>
                              {availableKitComponents.map((product) => (
                                <option key={product.id} value={product.id}>
                                  {product.code} - {product.name}
                                </option>
                              ))}
                            </select>
                          </SelectWrap>
                        </Field>
                        <Field label="Cantidad">
                          <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={item.quantity}
                            onChange={(event) => updateKitItem(item.key, { quantity: event.target.value })}
                            className={inputClass}
                          />
                        </Field>
                        <button
                          type="button"
                          onClick={() => removeKitItem(item.key)}
                          className="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-stroke text-red-500 hover:border-red-500 dark:border-strokedark"
                          aria-label="Quitar componente del combo/kit"
                        >
                          <FiTrash2 className="h-5 w-5" />
                        </button>
                      </div>
                    ))}
                    {draft.kitItems.length === 0 && (
                      <p className="rounded-lg border border-dashed border-stroke px-4 py-6 text-center text-sm text-slate-500 dark:border-strokedark">
                        No hay componentes configurados.
                      </p>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={addKitItem}
                    className="mt-4 inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2.5 text-sm font-semibold text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
                  >
                    <FiPlus className="h-4 w-4" /> Agregar componente
                  </button>
                </FormCard>
              )}

              <FormCard title="Observaciones">
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                  <Field label="Notas">
                    <textarea
                      value={draft.notes}
                      onChange={(event) => updateDraft('notes', event.target.value)}
                      className={textareaClass}
                    />
                  </Field>
                  <Field label="Observaciones">
                    <textarea
                      value={draft.observations}
                      onChange={(event) =>
                        updateDraft('observations', event.target.value)
                      }
                      className={textareaClass}
                    />
                  </Field>
                </div>
              </FormCard>
            </div>

            <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
              >
                Cancelar
              </button>
              {dialogMode === 'create' && (
                <button
                  type="button"
                  disabled={submitting || Object.keys(draftErrors).length > 0}
                  onClick={() => void saveProduct(true)}
                  className="inline-flex h-12 items-center justify-center gap-3 rounded-lg border border-primary px-6 text-sm font-bold text-primary transition hover:bg-primary hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <FiPlus className="h-5 w-5" />
                  Guardar y crear otro
                </button>
              )}
              <button
                type="submit"
                disabled={submitting || Object.keys(draftErrors).length > 0}
                className="inline-flex h-12 items-center justify-center gap-3 rounded-lg bg-primary px-6 text-sm font-bold text-white transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <FiSave className="h-5 w-5" />
                {submitting ? 'Guardando' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}

      {dialogMode === 'kardex' && selectedProduct && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <div className="w-full max-w-4xl rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-start justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">
                  Kardex - {selectedProduct.name}
                </p>
                <p className="mt-1 text-sm font-semibold text-slate-500">
                  Movimientos registrados para {selectedProduct.code}
                </p>
              </div>
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {kardexError && (
              <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">
                {kardexError}
              </div>
            )}

            <div className="overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
              <table className="w-full min-w-[720px] table-auto">
                <thead>
                  <tr className="bg-gray-50 text-left dark:bg-meta-4">
                    <th className="px-4 py-3 text-xs font-black uppercase text-black dark:text-white">
                      Fecha
                    </th>
                    <th className="px-4 py-3 text-xs font-black uppercase text-black dark:text-white">
                      Detalle
                    </th>
                    <th className="px-4 py-3 text-xs font-black uppercase text-black dark:text-white">
                      Bodega
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-black uppercase text-black dark:text-white">
                      Entrada
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-black uppercase text-black dark:text-white">
                      Salida
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-black uppercase text-black dark:text-white">
                      Saldo
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {kardexLoading && (
                    <tr>
                      <td
                        colSpan={6}
                        className="px-4 py-10 text-center text-sm font-semibold text-slate-500"
                      >
                        Cargando movimientos...
                      </td>
                    </tr>
                  )}

                  {!kardexLoading &&
                    kardexRows.map((movement) => (
                      <tr
                        key={movement.id}
                        className="border-t border-stroke dark:border-strokedark"
                      >
                        <td className="px-4 py-3 text-sm font-semibold text-black dark:text-white">
                          {movement.date}
                        </td>
                        <td className="px-4 py-3 text-sm font-semibold text-black dark:text-white">
                          {movement.detail}
                        </td>
                        <td className="px-4 py-3 text-sm font-semibold text-black dark:text-white">
                          {movement.warehouse}
                        </td>
                        <td className="px-4 py-3 text-right text-sm font-black text-[#0F9F37]">
                          {formatQuantity(movement.input)}
                        </td>
                        <td className="px-4 py-3 text-right text-sm font-black text-red-500">
                          {formatQuantity(movement.output)}
                        </td>
                        <td className="px-4 py-3 text-right text-sm font-black text-primary">
                          {formatQuantity(movement.balance)}
                        </td>
                      </tr>
                    ))}

                  {!kardexLoading && kardexRows.length === 0 && (
                    <tr>
                      <td
                        colSpan={6}
                        className="px-4 py-10 text-center text-sm font-semibold text-slate-500"
                      >
                        Sin movimientos registrados.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Products;
