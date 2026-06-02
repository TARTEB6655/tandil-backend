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
            'subtitle'    => $g->subtitle,
            'input_type'  => $g->input_type,
            'is_required' => $g->is_required,
            'sort_order'  => $g->sort_order,
            'options'     => $g->options->map(fn($o) => [
                'id'             => $o->id,
                'temp_key'       => 'opt_' . $o->id,
                'label'          => $o->label,
                'subtitle'       => $o->subtitle,
                'price_modifier' => $o->price_modifier,
                'image_path'     => $o->image_path,
                'image_url'      => $o->image_url,
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

    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-3">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Product options</h2>
        </div>
        <button type="button" onclick="addOptionGroup()"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors shadow-sm">
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
    var uniqueCounter = Date.now();

    function nextTempKey() {
        uniqueCounter += 1;
        return 'opt_' + uniqueCounter;
    }

    function normalizeGroups() {
        groups.forEach(function (g, gi) {
            g.sort_order = gi;
            g.subtitle = g.subtitle || '';
            if (!Array.isArray(g.options)) g.options = [];
            g.options.forEach(function (opt, oi) {
                opt.sort_order = oi;
                opt.subtitle = opt.subtitle || '';
                opt.image_path = opt.image_path || '';
                if (!opt.temp_key) opt.temp_key = nextTempKey();
            });
        });
    }

    function toggleVariableBuilder() {
        var isVariable = document.querySelector('input[name="product_type"]:checked')?.value === 'variable';
        document.getElementById('variableBuilderSection').classList.toggle('hidden', !isVariable);
    }
    window.toggleVariableBuilder = toggleVariableBuilder;

    function renderGroups() {
        var list = document.getElementById('optionGroupsList');
        list.innerHTML = '';
        normalizeGroups();
        groups.forEach(function (g, gi) {
            list.appendChild(buildGroupEl(g, gi));
        });
        syncJson();
    }

    function buildGroupEl(g, gi) {
        var div = document.createElement('div');
        div.className = 'border border-gray-100 dark:border-gray-700 rounded-xl p-4 space-y-3 bg-gray-50/70 dark:bg-gray-700/30 shadow-sm';
        div.dataset.groupIndex = gi;

        var header = document.createElement('div');
        header.className = 'flex items-start gap-3';

        var titleWrap = document.createElement('div');
        titleWrap.className = 'flex-1 space-y-2';

        var nameLabel = document.createElement('div');
        nameLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        nameLabel.textContent = 'Group title';

        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.placeholder = 'e.g. Packaging type';
        nameInput.value = g.name || '';
        nameInput.className = 'flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        nameInput.addEventListener('input', function () {
            groups[gi].name = this.value;
            syncJson();
        });

        var subtitleLabel = document.createElement('div');
        subtitleLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        subtitleLabel.textContent = 'Group subtitle';

        var subtitleInput = document.createElement('input');
        subtitleInput.type = 'text';
        subtitleInput.placeholder = 'e.g. Required - Select one';
        subtitleInput.value = g.subtitle || '';
        subtitleInput.className = 'flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        subtitleInput.addEventListener('input', function () {
            groups[gi].subtitle = this.value;
            syncJson();
        });

        titleWrap.appendChild(nameLabel);
        titleWrap.appendChild(nameInput);
        titleWrap.appendChild(subtitleLabel);
        titleWrap.appendChild(subtitleInput);

        var controlsWrap = document.createElement('div');
        controlsWrap.className = 'flex items-center gap-2 pt-5';

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
        rmBtn.className = 'inline-flex items-center justify-center w-8 h-8 rounded-md text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-lg font-bold leading-none';
        rmBtn.addEventListener('click', function () {
            groups.splice(gi, 1);
            renderGroups();
        });

        controlsWrap.appendChild(typeSelect);
        controlsWrap.appendChild(reqLabel);
        controlsWrap.appendChild(rmBtn);

        header.appendChild(titleWrap);
        header.appendChild(controlsWrap);
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
        addOptBtn.className = 'inline-flex items-center ml-2 px-2.5 py-1.5 rounded-md text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-200 dark:bg-indigo-900/40 dark:hover:bg-indigo-900/60 transition-colors';
        addOptBtn.addEventListener('click', function () {
            if (!groups[gi].options) groups[gi].options = [];
            groups[gi].options.push({
                temp_key: nextTempKey(),
                label: '',
                subtitle: '',
                price_modifier: 0,
                image_path: '',
                sort_order: groups[gi].options.length
            });
            renderGroups();
        });
        div.appendChild(addOptBtn);

        return div;
    }

    function buildOptionEl(gi, oi, opt) {
        var row = document.createElement('div');
        row.className = 'relative border border-gray-100 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-700/50 space-y-2';

        var rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.innerHTML = '&times;';
        rmBtn.className = 'absolute top-2 right-2 inline-flex items-center justify-center w-7 h-7 rounded-md text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 font-bold text-lg leading-none';
        rmBtn.addEventListener('click', function () {
            groups[gi].options.splice(oi, 1);
            renderGroups();
        });

        var optionTitle = document.createElement('div');
        optionTitle.className = 'text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide';
        optionTitle.textContent = 'Option ' + (oi + 1);

        var nameLabel = document.createElement('div');
        nameLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        nameLabel.textContent = 'Name';

        var label = document.createElement('input');
        label.type = 'text';
        label.placeholder = 'e.g. Foam';
        label.value = opt.label || '';
        label.className = 'w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        label.addEventListener('input', function () {
            groups[gi].options[oi].label = this.value;
            syncJson();
        });

        var subtitleLabel = document.createElement('div');
        subtitleLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        subtitleLabel.textContent = 'Option subtitle (optional)';

        var subtitleInput = document.createElement('input');
        subtitleInput.type = 'text';
        subtitleInput.placeholder = 'e.g. age 3-4';
        subtitleInput.value = opt.subtitle || '';
        subtitleInput.className = 'w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        subtitleInput.addEventListener('input', function () {
            groups[gi].options[oi].subtitle = this.value;
            syncJson();
        });

        var priceLabel = document.createElement('div');
        priceLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        priceLabel.textContent = 'Extra price (AED, 0 = Free)';

        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.step = '0.01';
        priceInput.value = opt.price_modifier || 0;
        priceInput.className = 'w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100';
        priceInput.addEventListener('input', function () {
            groups[gi].options[oi].price_modifier = parseFloat(this.value) || 0;
            syncJson();
        });

        var imageLabel = document.createElement('div');
        imageLabel.className = 'text-xs font-semibold text-gray-600 dark:text-gray-300';
        imageLabel.textContent = 'Option image (optional)';

        var imageMeta = document.createElement('div');
        imageMeta.className = 'text-[11px] text-gray-500 dark:text-gray-300';
        imageMeta.textContent = opt.image_path
            ? 'Current: ' + (opt.image_path.length > 42 ? opt.image_path.substring(0, 42) + '...' : opt.image_path)
            : '';

        var imagePreview = document.createElement('div');
        imagePreview.className = 'w-16 h-16 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-100 dark:bg-gray-700 overflow-hidden flex items-center justify-center';
        var imagePreviewImg = document.createElement('img');
        imagePreviewImg.className = 'w-full h-full object-cover hidden';
        imagePreview.appendChild(imagePreviewImg);

        var imagePreviewPlaceholder = document.createElement('span');
        imagePreviewPlaceholder.className = 'hidden';
        imagePreviewPlaceholder.textContent = '';
        imagePreview.appendChild(imagePreviewPlaceholder);

        function setPreview(src) {
            if (src) {
                imagePreviewImg.src = src;
                imagePreviewImg.classList.remove('hidden');
                imagePreviewPlaceholder.classList.add('hidden');
            } else {
                imagePreviewImg.src = '';
                imagePreviewImg.classList.add('hidden');
                imagePreviewPlaceholder.classList.remove('hidden');
            }
        }
        setPreview(opt.image_url || '');

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.name = 'option_images[' + opt.temp_key + ']';
        fileInput.className = 'block w-full text-xs text-gray-600 dark:text-gray-300 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-green-700 file:text-white hover:file:bg-green-800';
        fileInput.addEventListener('change', function () {
            var f = this.files && this.files[0] ? this.files[0] : null;
            if (f) {
                imageMeta.textContent = 'Selected: ' + f.name;
                setPreview(URL.createObjectURL(f));
            } else {
                imageMeta.textContent = opt.image_path ? ('Current: ' + opt.image_path) : '';
                setPreview(opt.image_url || '');
            }
        });

        row.appendChild(rmBtn);
        row.appendChild(optionTitle);
        row.appendChild(nameLabel);
        row.appendChild(label);
        row.appendChild(subtitleLabel);
        row.appendChild(subtitleInput);
        row.appendChild(priceLabel);
        row.appendChild(priceInput);
        row.appendChild(imageLabel);
        row.appendChild(imagePreview);
        row.appendChild(imageMeta);
        row.appendChild(fileInput);
        return row;
    }

    function addOptionGroup() {
        groups.push({
            name: '',
            subtitle: '',
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
        normalizeGroups();
        renderGroups();
        toggleVariableBuilder();
    });
})();
</script>
