{{--
    Variable product options & variants builder.
    Included in create.blade.php and edit.blade.php.

    Props:
      $product (optional) – existing Product model with optionGroups.options loaded.
--}}
@php
    $existingGroups = isset($product) && $product->isVariable()
        ? $product->optionGroups->map(fn($g) => [
            'id'          => $g->id,
            'name'        => $g->name,
            'input_type'  => $g->input_type,
            'is_required' => $g->is_required,
            'sort_order'  => $g->sort_order,
            'options'     => $g->options->map(fn($o) => [
                'id'             => $o->id,
                'label'          => $o->label,
                'price_modifier' => $o->price_modifier,
                'sort_order'     => $o->sort_order,
            ])->values()->all(),
        ])->values()->all()
        : [];
@endphp

{{-- Product type selector card --}}
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6" id="productTypeCard">
    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Product type</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        Choose <strong>Simple</strong> for a single price/stock product, or <strong>Variable</strong> to add option groups (e.g. Packaging, Cutting, Weight).
    </p>
    <div class="flex gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="product_type" value="simple"
                   {{ (old('product_type', $product->product_type ?? 'simple') === 'simple') ? 'checked' : '' }}
                   onchange="toggleVariableBuilder()"
                   class="text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Simple product</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="product_type" value="variable"
                   {{ (old('product_type', $product->product_type ?? 'simple') === 'variable') ? 'checked' : '' }}
                   onchange="toggleVariableBuilder()"
                   class="text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Variable product</span>
        </label>
    </div>
</div>

{{-- Variable builder (hidden for simple products) --}}
<div id="variableBuilderSection"
     class="{{ (old('product_type', $product->product_type ?? 'simple') === 'variable') ? '' : 'hidden' }} bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Option groups</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Add option groups like "Packaging type", "Cutting", "Weight". Each group has selectable options.
            </p>
        </div>
        <button type="button" onclick="addOptionGroup()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add group
        </button>
    </div>

    <div id="optionGroupsList" class="space-y-4">
        {{-- Populated by JS --}}
    </div>

    {{-- Hidden JSON field submitted with the form --}}
    <input type="hidden" name="option_groups_json" id="optionGroupsJson">
</div>

<script>
(function () {
    // Bootstrap from server-side existing data (edit mode)
    var serverGroups = @json($existingGroups);
    var groups = serverGroups.length ? serverGroups : [];
    var groupCounter = groups.length;

    function toggleVariableBuilder() {
        var isVariable = document.querySelector('input[name="product_type"]:checked')?.value === 'variable';
        document.getElementById('variableBuilderSection').classList.toggle('hidden', !isVariable);
    }
    window.toggleVariableBuilder = toggleVariableBuilder;

    function renderGroups() {
        var list = document.getElementById('optionGroupsList');
        list.innerHTML = '';
        groups.forEach(function (g, gi) {
            list.appendChild(buildGroupEl(g, gi));
        });
        syncJson();
    }

    function buildGroupEl(g, gi) {
        var div = document.createElement('div');
        div.className = 'border border-gray-200 dark:border-gray-600 rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-700/40';
        div.dataset.groupIndex = gi;

        var header = document.createElement('div');
        header.className = 'flex items-center gap-3';

        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.placeholder = 'Group name (e.g. Packaging type)';
        nameInput.value = g.name || '';
        nameInput.className = 'flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        nameInput.addEventListener('input', function () {
            groups[gi].name = this.value;
            syncJson();
        });

        // input_type
        var typeSelect = document.createElement('select');
        typeSelect.className = 'rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        [['single', 'Select one'], ['multi', 'Select many']].forEach(function (opt) {
            var o = document.createElement('option');
            o.value = opt[0]; o.textContent = opt[1];
            if (g.input_type === opt[0]) o.selected = true;
            typeSelect.appendChild(o);
        });
        typeSelect.addEventListener('change', function () {
            groups[gi].input_type = this.value;
            syncJson();
        });

        // required
        var reqLabel = document.createElement('label');
        reqLabel.className = 'flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300 cursor-pointer';
        var reqChk = document.createElement('input');
        reqChk.type = 'checkbox';
        reqChk.checked = g.is_required !== false;
        reqChk.className = 'rounded text-indigo-600';
        reqChk.addEventListener('change', function () {
            groups[gi].is_required = this.checked;
            syncJson();
        });
        reqLabel.appendChild(reqChk);
        reqLabel.appendChild(document.createTextNode('Required'));

        // remove btn
        var rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.innerHTML = '&times;';
        rmBtn.className = 'text-red-500 hover:text-red-700 text-xl font-bold leading-none px-1';
        rmBtn.addEventListener('click', function () {
            groups.splice(gi, 1);
            renderGroups();
        });

        header.appendChild(nameInput);
        header.appendChild(typeSelect);
        header.appendChild(reqLabel);
        header.appendChild(rmBtn);
        div.appendChild(header);

        // Options list
        var optsContainer = document.createElement('div');
        optsContainer.className = 'space-y-2 ml-2';
        (g.options || []).forEach(function (opt, oi) {
            optsContainer.appendChild(buildOptionEl(gi, oi, opt));
        });
        div.appendChild(optsContainer);

        // Add option button
        var addOptBtn = document.createElement('button');
        addOptBtn.type = 'button';
        addOptBtn.textContent = '+ Add option';
        addOptBtn.className = 'text-xs text-indigo-600 hover:text-indigo-800 font-medium ml-2';
        addOptBtn.addEventListener('click', function () {
            if (!groups[gi].options) groups[gi].options = [];
            groups[gi].options.push({ label: '', price_modifier: 0, sort_order: groups[gi].options.length });
            renderGroups();
        });
        div.appendChild(addOptBtn);

        return div;
    }

    function buildOptionEl(gi, oi, opt) {
        var row = document.createElement('div');
        row.className = 'flex items-center gap-2';

        var label = document.createElement('input');
        label.type = 'text';
        label.placeholder = 'Option label (e.g. in bag)';
        label.value = opt.label || '';
        label.className = 'flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        label.addEventListener('input', function () {
            groups[gi].options[oi].label = this.value;
            syncJson();
        });

        var priceLabel = document.createElement('span');
        priceLabel.className = 'text-xs text-gray-500 whitespace-nowrap';
        priceLabel.textContent = '+price';

        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.step = '0.01';
        priceInput.value = opt.price_modifier || 0;
        priceInput.className = 'w-20 rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        priceInput.addEventListener('input', function () {
            groups[gi].options[oi].price_modifier = parseFloat(this.value) || 0;
            syncJson();
        });

        var rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.innerHTML = '&times;';
        rmBtn.className = 'text-red-400 hover:text-red-600 font-bold text-lg leading-none';
        rmBtn.addEventListener('click', function () {
            groups[gi].options.splice(oi, 1);
            renderGroups();
        });

        row.appendChild(label);
        row.appendChild(priceLabel);
        row.appendChild(priceInput);
        row.appendChild(rmBtn);
        return row;
    }

    function addOptionGroup() {
        groups.push({
            name: '',
            input_type: 'single',
            is_required: true,
            sort_order: groups.length,
            options: [],
        });
        renderGroups();
    }
    window.addOptionGroup = addOptionGroup;

    function syncJson() {
        var el = document.getElementById('optionGroupsJson');
        if (el) el.value = JSON.stringify(groups);
    }

    // Init
    document.addEventListener('DOMContentLoaded', function () {
        renderGroups();
        toggleVariableBuilder();
    });
})();
</script>
