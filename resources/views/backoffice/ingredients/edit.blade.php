@extends('backoffice.layouts.app')

@section('content')

<div class="content-card">

    <div style="padding:28px 30px 0;">

        <a href="{{ route('backoffice.ingredients.index') }}"
           class="btn btn-dark"
           style="text-decoration:none;display:inline-flex;margin-bottom:18px;">
            ← Back
        </a>

        <h1 style="margin:0;font-size:32px;font-weight:900;color:#111827;">
            Edit Ingredient
        </h1>

        <p style="margin-top:8px;color:#6b7280;font-weight:600;">
            Update ingredient information and outlet availability.
        </p>

    </div>


    <div style="padding:28px 30px 34px;">

        @if($errors->any())
            <div style="
                background:#ffe8e8;
                color:#9b1c1c;
                border:1px solid #fecaca;
                border-radius:16px;
                padding:16px;
                margin-bottom:20px;
                font-weight:700;
            ">
                <ul style="margin:0;padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        <div style="
            background:white;
            border:1px solid #e8edf4;
            border-radius:26px;
            padding:24px;
            box-shadow:0 16px 34px rgba(15,23,42,.08);
        ">

            <form method="POST"
                  action="{{ route('backoffice.ingredients.update', $ingredient) }}">

                @csrf
                @method('PUT')


                <div style="
                    display:grid;
                    grid-template-columns:repeat(2,minmax(0,1fr));
                    gap:18px;
                ">


                    <div>
                        <label style="font-weight:800;">
                            Category
                        </label>

                        <select name="ingredient_category_id"
                                required
                                style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">
                            <option value="">Pilih category</option>

                            @foreach($categories as $category)

                                <option value="{{ $category->id }}"
                                    @selected(old('ingredient_category_id',$ingredient->ingredient_category_id)==$category->id)>
                                    {{ $category->name }}
                                </option>

                            @endforeach

                        </select>
                    </div>



                    <div>
                        <label style="font-weight:800;">
                            Unit
                        </label>

                        <input
                            type="text"
                            name="unit"
                            value="{{ old('unit',$ingredient->unit) }}"
                            required
                            style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">
                    </div>



                    <div style="grid-column:1/-1;">
                        <label style="font-weight:800;">
                            Ingredient Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            value="{{ old('name',$ingredient->name) }}"
                            required
                            style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">
                    </div>



                    <div>
                        <label style="font-weight:800;">
                            Tipe Bahan
                        </label>

                        <select name="ingredient_type"
                                required
                                style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">

                            @foreach($ingredientTypeOptions as $typeValue=>$typeLabel)

                                <option value="{{ $typeValue }}"
                                    @selected(old('ingredient_type',$ingredient->ingredient_type)==$typeValue)>
                                    {{ $typeLabel }}
                                </option>

                            @endforeach

                        </select>

                    </div>



                    <div>
                        <label style="font-weight:800;">
                            Minimum Stock
                        </label>

                        <input
                            type="number"
                            name="minimum_stock"
                            step="0.01"
                            min="0"
                            value="{{ old('minimum_stock',$ingredient->minimum_stock) }}"
                            required
                            style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">
                    </div>



                    <div>
                        <label style="font-weight:800;">
                            Cost per Unit
                        </label>

                        <input
                            type="number"
                            name="cost_per_unit"
                            step="0.01"
                            min="0"
                            value="{{ old('cost_per_unit',$ingredient->cost_per_unit) }}"
                            required
                            style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">
                    </div>



                    <div style="grid-column:1/-1;">

                        <label style="font-weight:900;">
                            Outlet Availability
                        </label>

                        <div style="
                            margin-top:12px;
                            display:grid;
                            grid-template-columns:repeat(3,minmax(0,1fr));
                            gap:14px;
                        ">

                            @foreach($outlets as $outlet)

                            <label style="
                                border:1px solid #e5e7eb;
                                border-radius:18px;
                                padding:16px;
                                cursor:pointer;
                                background:#fff;
                                display:flex;
                                align-items:center;
                                gap:12px;
                            ">

                                <input
                                    type="checkbox"
                                    name="outlet_ids[]"
                                    value="{{ $outlet->id }}"
                                    @checked($ingredient->outlets->contains('id',$outlet->id))
                                >

                                <div>
                                    <div style="font-weight:900;">
                                        {{ $outlet->name }}
                                    </div>

                                    <small style="color:#6b7280;">
                                        Active Outlet
                                    </small>
                                </div>

                            </label>

                            @endforeach

                        </div>

                    </div>



                    <div>

                        <label style="font-weight:800;">
                            Status
                        </label>

                        <select name="is_active"
                                required
                                style="width:100%;margin-top:8px;padding:14px;border-radius:14px;border:1px solid #d1d5db;">

                            <option value="1"
                                @selected(old('is_active',(string)(int)$ingredient->is_active)=='1')>
                                Active
                            </option>

                            <option value="0"
                                @selected(old('is_active',(string)(int)$ingredient->is_active)=='0')>
                                Inactive
                            </option>

                        </select>

                    </div>


                </div>


                <div style="
                    margin-top:28px;
                    display:flex;
                    gap:12px;
                    align-items:center;
                ">

                    <button
                        type="submit"
                        style="
                            border:none;
                            cursor:pointer;
                            padding:13px 24px;
                            border-radius:14px;
                            background:linear-gradient(135deg,#2563eb,#3b82f6);
                            color:white;
                            font-size:14px;
                            font-weight:800;
                            box-shadow:0 12px 25px rgba(37,99,235,.25);
                        "
                    >
                        ✓ Update Ingredient
                    </button>


                    <a
                        href="{{ route('backoffice.ingredients.index') }}"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            padding:13px 24px;
                            border-radius:14px;
                            background:#111827;
                            color:white;
                            text-decoration:none;
                            font-size:14px;
                            font-weight:800;
                            box-shadow:0 10px 20px rgba(15,23,42,.15);
                        "
                    >
                        Cancel
                    </a>

                </div>


            </form>

        </div>

    </div>

</div>


@endsection
