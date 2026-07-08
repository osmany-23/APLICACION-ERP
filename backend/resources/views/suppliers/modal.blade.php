<div id="supplier-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4 py-8">
    <div class="w-full max-w-5xl overflow-hidden rounded-[32px] bg-white shadow-2xl ring-1 ring-slate-200 sm:mx-auto">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.15em] text-slate-500">Nuevo proveedor</p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">Crear proveedor limpio y moderno</h2>
            </div>
            <button type="button" onclick="document.getElementById('supplier-modal').classList.add('hidden')" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-900">
                ✕
            </button>
        </div>
        <div class="grid grid-cols-1 gap-6 px-6 py-8 lg:grid-cols-[320px_1fr]">
            <aside class="space-y-4 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-slate-900">Secciones rápidas</p>
                    <p class="text-sm leading-6 text-slate-500">Campos agrupados para la mejor experiencia. Llena solo lo necesario y el resto queda opcional.</p>
                </div>
                <div class="space-y-3 text-sm text-slate-600">
                    <button type="button" class="section-tab w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-left font-medium text-slate-900" data-section="general">General</button>
                    <button type="button" class="section-tab w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-left font-medium text-slate-900" data-section="contact">Contacto</button>
                    <button type="button" class="section-tab w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-left font-medium text-slate-900" data-section="finance">Finanzas</button>
                </div>
            </aside>
            <form method="POST" action="{{ route('suppliers.store') }}" enctype="multipart/form-data" class="space-y-8">
                @csrf
                <div class="space-y-8 rounded-3xl border border-slate-200 bg-slate-50 p-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Nombre</label>
                            <input name="name" type="text" maxlength="150" value="{{ old('name') }}" required class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">RUC / Cédula / Pasaporte</label>
                            <input name="tax_id" type="text" maxlength="50" value="{{ old('tax_id') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">RUC / Cédula / Pasaporte</label>
                            <input name="tax_id" type="text" maxlength="50" value="{{ old('tax_id') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Teléfono</label>
                            <input name="phone" type="text" maxlength="30" value="{{ old('phone') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Correo electrónico</label>
                            <input name="email" type="email" maxlength="120" value="{{ old('email') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Sitio web</label>
                            <input name="website" type="url" maxlength="200" value="{{ old('website') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-700">Dirección</label>
                        <textarea name="address" rows="3" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ old('address') }}</textarea>
                    </div>
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-700">Estado</label>
                        <div class="flex items-center gap-4">
                            <input type="hidden" name="status" id="supplier-status-hidden" value="1" />
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input id="supplier-status-checkbox" type="checkbox" class="peer sr-only" checked />
                                <div class="h-8 w-14 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500"></div>
                                <span class="pointer-events-none absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-sm transition peer-checked:translate-x-6"></span>
                            </label>
                            <span id="supplier-status-label" class="text-sm font-medium text-slate-700">Activo</span>
                        </div>
                    </div>
                </div>

                <div id="section-general" class="section-panel space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Persona de contacto</label>
                            <input name="contact_person" type="text" maxlength="120" value="{{ old('contact_person') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Teléfono de contacto</label>
                            <input name="contact_phone" type="text" maxlength="30" value="{{ old('contact_phone') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-700">Correo de contacto</label>
                        <input name="contact_email" type="email" maxlength="120" value="{{ old('contact_email') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                    </div>
                </div>

                <div id="section-contact" class="section-panel hidden space-y-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Banco</label>
                            <input name="bank_name" type="text" maxlength="120" value="{{ old('bank_name') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Cuenta bancaria</label>
                            <input name="bank_account" type="text" maxlength="100" value="{{ old('bank_account') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">SWIFT</label>
                            <input name="swift" type="text" maxlength="20" value="{{ old('swift') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">IBAN</label>
                            <input name="iban" type="text" maxlength="60" value="{{ old('iban') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Límite de crédito</label>
                            <input name="credit_limit" type="number" step="0.01" value="{{ old('credit_limit') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Días de crédito</label>
                            <input name="credit_days" type="number" value="{{ old('credit_days') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold text-slate-700">Días de pago</label>
                            <input name="payment_days" type="number" value="{{ old('payment_days') }}" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
                        </div>
                    </div>
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-700">Notas</label>
                        <textarea name="notes" rows="4" class="w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-6">
                    <button type="button" onclick="document.getElementById('supplier-modal').classList.add('hidden')" class="rounded-3xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="rounded-3xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Guardar proveedor</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.section-tab').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.section-tab').forEach((tab) => tab.classList.remove('bg-slate-900', 'text-white'));
                document.querySelectorAll('.section-panel').forEach((panel) => panel.classList.add('hidden'));

                button.classList.add('bg-slate-900', 'text-white');
                document.getElementById('section-' + button.dataset.section).classList.remove('hidden');
            });
        });

        document.querySelector('.section-tab').click();

        const statusCheckbox = document.querySelector('#supplier-status-checkbox');
        const statusHidden = document.querySelector('#supplier-status-hidden');
        const statusLabel = document.querySelector('#supplier-status-label');

        if (statusCheckbox && statusHidden && statusLabel) {
            const updateStatus = () => {
                const active = statusCheckbox.checked;
                statusHidden.value = active ? '1' : '0';
                statusLabel.textContent = active ? 'Activo' : 'Inactivo';
            };

            statusCheckbox.addEventListener('change', updateStatus);
            updateStatus();
        }
    </script>
</div>
