// Precios por volumen: un producto puede tener varios "tipos de precio"
// (Mayorista, Distribuidor, etc.), cada uno con una cantidad minima a
// partir de la cual aplica. Esta logica de resolucion (elegir el mejor
// tier aplicable segun la cantidad) se comparte entre POS y el modulo de
// Ventas para que ambos autocompleten el mismo precio — y replica
// EXACTAMENTE el criterio de desempate que usa el backend en
// SalesService::resolveMinimumAllowedPrice(), que es quien realmente
// valida/autoriza la venta. Esto es solo para que la UI anticipe (en vivo)
// lo que el servidor va a aceptar; el servidor sigue siendo la autoridad.
export type PriceTier = {
  price_list_id: number;
  price_list_name: string;
  min_quantity: number;
  price: number;
  is_active?: boolean;
};

export type TierablePricedProduct = {
  sale_price: number;
  price_tiers?: PriceTier[] | null;
};

export function resolveTierPrice(product: TierablePricedProduct, quantity: number): number {
  const applicable = (product.price_tiers ?? []).filter((tier) => quantity >= tier.min_quantity);

  if (applicable.length === 0) {
    return product.sale_price;
  }

  // Mejor tier = el de mayor min_quantity que la cantidad todavia alcanza
  // (el escalon mas profundo); si dos tipos de precio distintos empatan en
  // min_quantity, gana el de menor precio.
  const best = [...applicable].sort((a, b) =>
    b.min_quantity !== a.min_quantity ? b.min_quantity - a.min_quantity : a.price - b.price,
  )[0];

  return best.price;
}
