import { useState } from 'react';
import { FiDelete, FiX } from 'react-icons/fi';

const KEYS = [
  ['C', '±', '%', '÷'],
  ['7', '8', '9', '×'],
  ['4', '5', '6', '-'],
  ['1', '2', '3', '+'],
  ['0', '.', '⌫', '='],
];

function computeResult(expression: string): string {
  // Calculadora simple: solo digitos, punto y + - × ÷ encadenados de
  // izquierda a derecha (sin precedencia de operadores, como una
  // calculadora fisica basica de mostrador).
  const tokens = expression.match(/(\d+\.?\d*|\+|-|×|÷)/g);
  if (!tokens || tokens.length === 0) return '0';

  let result = parseFloat(tokens[0]) || 0;

  for (let i = 1; i < tokens.length; i += 2) {
    const operator = tokens[i];
    const value = parseFloat(tokens[i + 1]);
    if (Number.isNaN(value)) break;

    if (operator === '+') result += value;
    else if (operator === '-') result -= value;
    else if (operator === '×') result *= value;
    else if (operator === '÷') result = value !== 0 ? result / value : NaN;
  }

  return Number.isFinite(result) ? String(Math.round(result * 1e6) / 1e6) : 'Error';
}

export default function CalculatorModal({ onClose }: { onClose: () => void }) {
  const [expression, setExpression] = useState('');
  const [display, setDisplay] = useState('0');

  function handleKey(key: string) {
    if (key === 'C') {
      setExpression('');
      setDisplay('0');
      return;
    }

    if (key === '⌫') {
      const next = expression.slice(0, -1);
      setExpression(next);
      setDisplay(next || '0');
      return;
    }

    if (key === '=') {
      const result = computeResult(expression);
      setDisplay(result);
      setExpression(result === 'Error' ? '' : result);
      return;
    }

    if (key === '±') {
      setExpression((current) => (current.startsWith('-') ? current.slice(1) : `-${current}`));
      setDisplay((current) => (current.startsWith('-') ? current.slice(1) : `-${current}`));
      return;
    }

    if (key === '%') {
      const result = computeResult(expression);
      const percent = String((parseFloat(result) || 0) / 100);
      setExpression(percent);
      setDisplay(percent);
      return;
    }

    setExpression((current) => current + key);
    setDisplay((current) => (current === '0' ? key : current + key));
  }

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="w-full max-w-xs overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-slate-900 px-4 py-3 text-white">
          <span className="text-sm font-bold uppercase tracking-wide">Calculadora</span>
          <button type="button" onClick={onClose} className="rounded-full p-1 hover:bg-white/10">
            <FiX />
          </button>
        </div>

        <div className="bg-slate-900 px-4 pb-4">
          <div className="truncate rounded-lg bg-black/30 px-3 py-4 text-right text-3xl font-black text-white">{display}</div>
        </div>

        <div className="grid grid-cols-4 gap-2 p-3">
          {KEYS.flat().map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => handleKey(key)}
              className={`flex h-14 items-center justify-center rounded-xl text-lg font-bold transition active:scale-95 ${
                key === '='
                  ? 'bg-primary text-white hover:bg-opacity-90'
                  : ['+', '-', '×', '÷'].includes(key)
                    ? 'bg-slate-100 text-primary hover:bg-slate-200 dark:bg-meta-4 dark:text-white'
                    : ['C', '⌫', '%', '±'].includes(key)
                      ? 'bg-slate-100 text-red-500 hover:bg-slate-200 dark:bg-meta-4'
                      : 'bg-slate-50 text-black hover:bg-slate-100 dark:bg-boxdark-2 dark:text-white dark:hover:bg-meta-4'
              }`}
            >
              {key === '⌫' ? <FiDelete /> : key}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
