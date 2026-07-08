<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ $supplier ? 'Editar proveedor' : 'Nuevo proveedor' }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Administración de proveedores</p>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ $supplier ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50">Volver</a>
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                    Aquí puedes crear o actualizar registros de proveedores con código autogenerado y datos completos de contacto, pago y contabilidad.
                </div>
            </header>

            @if(session('success'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-slate-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-slate-800">
                    <p class="font-semibold text-rose-800">Corrige los errores antes de continuar.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <nav class="flex flex-wrap gap-2 text-sm font-medium text-slate-600" aria-label="Secciones del formulario">
                        <button type="button" class="tab-button rounded-full px-4 py-2 transition hover:bg-slate-100" data-tab="general">General</button>
                        <button type="button" class="tab-button rounded-full px-4 py-2 transition hover:bg-slate-100" data-tab="contact">Contacto</button>
                        <button type="button" class="tab-button rounded-full px-4 py-2 transition hover:bg-slate-100" data-tab="finanzas">Finanzas</button>
                    </nav>
                </div>

                <form method="POST" action="{{ $supplier ? route('suppliers.update', $supplier->id) : route('suppliers.store') }}" enctype="multipart/form-data" class="space-y-6 px-6 py-8">
                    @csrf
                    @if($supplier)
                        @method('PUT')
                    @endif

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Estado</label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach($statuses as $value => $label)
                                    <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 px-4 py-3 text-sm hover:border-slate-400">
                                        <input type="radio" name="status" value="{{ $value }}" class="h-4 w-4 text-slate-700" {{ old('status', $supplier?->status ?? 1) == $value ? 'checked' : '' }} />
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div id="general" class="tab-content">
                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Nombre</label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name', $supplier?->name ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    required
                                />
                            </div>

                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">RUC / Cédula / Pasaporte</label>
                                <input
                                    type="text"
                                    name="tax_id"
                                    value="{{ old('tax_id', $supplier?->tax_id ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Página web</label>
                                <input
                                    type="url"
                                    name="website"
                                    value="{{ old('website', $supplier?->website ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Dirección</label>
                            <textarea
                                name="address"
                                rows="3"
                                class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            >{{ old('address', $supplier?->address ?? '') }}</textarea>
                        </div>

                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Notas</label>
                            <textarea
                                name="notes"
                                rows="3"
                                class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            >{{ old('notes', $supplier?->notes ?? '') }}</textarea>
                        </div>
                    </div>

                    <div id="contact" class="tab-content hidden">
                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Persona de contacto</label>
                                <input
                                    type="text"
                                    name="contact_person"
                                    value="{{ old('contact_person', $supplier?->contact_person ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>

                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Teléfono de contacto</label>
                                <input
                                    type="text"
                                    name="contact_phone"
                                    value="{{ old('contact_phone', $supplier?->contact_phone ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Correo de contacto</label>
                                <input
                                    type="email"
                                    name="contact_email"
                                    value="{{ old('contact_email', $supplier?->contact_email ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>

                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Teléfono</label>
                                <input
                                    type="text"
                                    name="phone"
                                    value="{{ old('phone', $supplier?->phone ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Correo electrónico</label>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', $supplier?->email ?? '') }}"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            />
                        </div>
                    </div>

                    <div id="finanzas" class="tab-content hidden">
                        <div class="grid gap-6 xl:grid-cols-2">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Condición de pago</label>
                                <select name="payment_term_id" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                    <option value="">Sin condición</option>
                                    @foreach($paymentTerms as $term)
                                        <option value="{{ $term->id }}" {{ old('payment_term_id', $supplier?->payment_term_id ?? '') == $term->id ? 'selected' : '' }}>{{ $term->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-3">
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Límite de crédito</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    name="credit_limit"
                                    value="{{ old('credit_limit', $supplier?->credit_limit ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Días de crédito</label>
                                <input
                                    type="number"
                                    name="credit_days"
                                    value="{{ old('credit_days', $supplier?->credit_days ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-slate-700">Días de pago</label>
                                <input
                                    type="number"
                                    name="payment_days"
                                    value="{{ old('payment_days', $supplier?->payment_days ?? '') }}"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">Recuerda completar al menos nombre y estado.</p>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Guardar proveedor</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const buttons = document.querySelectorAll('.tab-button');
            const tabs = document.querySelectorAll('.tab-content');

            function setActiveTab(name) {
                buttons.forEach((button) => {
                    const active = button.dataset.tab === name;
                    button.classList.toggle('bg-slate-900', active);
                    button.classList.toggle('text-white', active);
                    button.classList.toggle('text-slate-600', !active);
                });

                tabs.forEach((section) => {
                    section.classList.toggle('hidden', section.id !== name);
                });
            }

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    setActiveTab(button.dataset.tab);
                });
            });

            setActiveTab('general');
        </script>
    </body>
</html>
