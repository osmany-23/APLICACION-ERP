// Codificador Code 39 (ISO/IEC 16388) para el codigo de barra del ticket de
// venta (ver components/receipt/ReceiptTicket.tsx). Se eligio Code 39 sobre
// Code 128 porque su charset (0-9, A-Z, espacio, - . $ / + %) cubre
// exactamente el formato de sale_number (ej. "FACT-0121651") y su tabla de
// patrones es mucho mas simple de verificar sin margen de error: cada
// caracter son 9 elementos (5 barras + 4 espacios, alternando, siempre
// empieza y termina en barra) donde exactamente 3 de los 9 son "anchos" (3
// modulos) y 6 son "angostos" (1 modulo) — 6*1 + 3*3 = 15 modulos por
// caracter, siempre.
//
// La tabla de abajo son los valores de codificacion tal cual los usa
// JsBarcode (github.com/lindell/JsBarcode, MIT, libreria de codigo de barra
// mas usada en npm) — cada decimal, convertido a binario, da directamente
// la secuencia de modulos del caracter (1 = modulo negro, 0 = modulo
// blanco): el bit mas significativo siempre cae en el primer modulo de la
// primera barra (una barra nunca tiene ancho cero), asi que no hace falta
// rellenar con ceros a la izquierda. Verificado a mano para el digito "0"
// (20957 -> binario "101000111011101" -> agrupado en corridas de
// barra/espacio: N N N W W N W N N, que coincide con la tabla de
// referencia estandar de Code 39 para ese caracter).
const CODE39_CHARACTERS = [
  '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
  'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
  'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
  '-', '.', ' ', '$', '/', '+', '%', '*',
];

const CODE39_ENCODINGS = [
  20957, 29783, 23639, 30485, 20951, 29813, 23669, 20855, 29789, 23645,
  29975, 23831, 30533, 22295, 30149, 24005, 21623, 29981, 23837, 22301,
  30023, 23879, 30545, 22343, 30161, 24017, 21959, 30065, 23921, 22385,
  29015, 18263, 29141, 17879, 29045, 18293, 17783, 29021, 18269, 17477,
  17489, 17681, 20753, 35770,
];

function moduleBitsFor(character: string): string {
  const index = CODE39_CHARACTERS.indexOf(character);
  return index === -1 ? '' : CODE39_ENCODINGS[index].toString(2);
}

/**
 * Devuelve la secuencia completa de modulos (string de '1'/'0', 1 = modulo
 * negro) para el valor dado, con asteriscos de inicio/fin y un modulo
 * blanco de separacion entre cada par de caracteres (incluyendo entre el
 * asterisco de inicio y el primer caracter, y entre el ultimo caracter y el
 * asterisco final — asi lo exige la especificacion Code 39, para que dos
 * barras de caracteres consecutivos nunca queden pegadas). Caracteres fuera
 * del charset soportado (0-9, A-Z, espacio, - . $ / + %) se descartan en
 * vez de romper el codigo completo.
 */
export function code39Modules(value: string): string {
  const sanitized = value.toUpperCase().replace(/[^0-9A-Z\-. $/+%]/g, '');
  const symbols = ['*', ...sanitized.split(''), '*'];

  return symbols
    .map(moduleBitsFor)
    .filter((bits) => bits !== '')
    .join('0');
}
