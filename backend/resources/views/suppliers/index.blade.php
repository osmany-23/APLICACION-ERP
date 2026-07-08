<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Proveedores</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-500">ERP / Proveedores</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Listado de proveedores</h1>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('supplier-modal').classList.remove('hidden')"
                    class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Nuevo proveedor
                </button>
            </header>

            @if(session('success'))
                <div class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-slate-900">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-700">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Proveedor</th>
                                <th class="px-6 py-4">Código</th>
                                <th class="px-6 py-4">Banco</th>
                                <th class="px-6 py-4">Contacto</th>
                                <th class="px-6 py-4">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach($suppliers as $supplier)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-11 w-11 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200">
                                                @if($supplier->image_url)
                                                    <img src="{{ $supplier->image_url }}" alt="Logo {{ $supplier->name }}" class="h-full w-full object-cover" />
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-slate-400">P</div>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $supplier->name }}</p>
                                                <p class="text-xs text-slate-500">{{ $supplier->email ?? 'Sin email' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                            {{ $supplier->code ?? '---' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700">{{ $supplier->bank_name ?: $supplier->bank_account ?: 'Sin banco' }}</td>
                                    <td class="px-6 py-4 text-slate-700">{{ $supplier->contact_person ?: 'Sin contacto' }}</td>
                                    <td class="px-6 py-4">
                                        <form method="POST" action="{{ route('suppliers.toggleStatus', $supplier->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $supplier->status ? 0 : 1 }}" />
                                            <button type="submit" class="relative inline-flex h-9 w-16 items-center rounded-full transition duration-200 {{ $supplier->status ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                                <span class="absolute left-1 h-7 w-7 rounded-full bg-white shadow-sm transition-transform duration-200 {{ $supplier->status ? 'translate-x-7' : 'translate-x-0' }}"></span>
                                                <span class="ml-2 text-xs font-semibold text-white">{{ $supplier->status ? 'Activo' : 'Inactivo' }}</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('suppliers.modal')
    </body>
</html>
