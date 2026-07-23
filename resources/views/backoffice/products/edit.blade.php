@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Edit Product - Back Office ATG POS';
@endphp

@section('content')

<style>
    .product-shell {
        display:grid;
        gap:22px;
    }

    .page-header {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:16px;
        flex-wrap:wrap;
    }

    .page-title {
        margin:0;
        font-size:38px;
        font-weight:800;
        letter-spacing:-0.04em;
        color:#111827;
    }

    .page-subtitle {
        margin-top:8px;
        color:#6b7280;
        font-size:15px;
    }

    .btn {
        min-height:42px;
        padding:0 18px;
        border-radius:14px;
        border:0;
        cursor:pointer;
        font-size:13px;
        font-weight:800;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        color:white;
    }

    .btn-dark {
        background:#111827;
    }

    .btn-primary {
        background:linear-gradient(135deg,#1d4ed8,#2563eb);
    }

    .card {
        background:white;
        border-radius:24px;
        border:1px solid #e8edf4;
        padding:24px;
        box-shadow:0 12px 30px rgba(15,23,42,.05);
    }

    .grid {
        display:grid;
        grid-template-columns:repeat(2,1fr);
        gap:18px;
    }

    .field.full {
        grid-column:1/-1;
    }

    label {
        display:block;
        margin-bottom:8px;
        font-size:13px;
        font-weight:800;
        color:#111827;
    }

    input,
    select,
    textarea {
        width:100%;
        box-sizing:border-box;
        border:1px solid #d1d5db;
        border-radius:14px;
        padding:13px;
        font-size:14px;
    }

    textarea {
        min-height:120px;
    }

    .outlet-grid {
        display:grid;
        grid-template-columns:repeat(2,1fr);
        gap:12px;
    }

    .outlet-card {
        display:flex;
        align-items:center;
        gap:12px;
        border:1px solid #e5e7eb;
        padding:14px;
        border-radius:16px;
        cursor:pointer;
    }

    .outlet-card:hover {
        background:#f8fafc;
    }

    .outlet-card input {
        width:auto;
    }

    .outlet-name {
        font-weight:800;
        color:#111827;
    }

    .outlet-desc {
        font-size:12px;
        color:#6b7280;
    }

    .actions {
        display:flex;
        gap:12px;
        margin-top:24px;
    }

    .alert {
        background:#fff1f1;
        color:#b42318;
        border-radius:16px;
        padding:16px;
        margin-bottom:20px;
        font-weight:700;
    }

    @media(max-width:800px){
        .grid,
        .outlet-grid {
            grid-template-columns:1fr;
        }
    }
</style>


<div class="product-shell">

    <div class="page-header">

        <div>
            <h1 class="page-title">
                Edit Product
            </h1>

            <p class="page-subtitle">
                Update product information and outlet availability.
            </p>
        </div>


        <a href="{{ route('backoffice.products.index') }}"
           class="btn btn-dark">
            Kembali
        </a>

    </div>


    @if($errors->any())

        <div class="alert">

            <ul style="margin:0;padding-left:18px;">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif



    <div class="card">


        <form method="POST"
              action="{{ route('backoffice.products.update', $product->id) }}">

            @csrf
            @method('PUT')


            <div class="grid">


                <div class="field">

                    <label>
                        Brand
                    </label>

                    <select name="brand_id" required>

                        @foreach($brands as $brand)

                            <option value="{{ $brand->id }}"
                                @selected(old('brand_id',$product->brand_id)==$brand->id)>

                                {{ $brand->name }}

                            </option>

                        @endforeach

                    </select>

                </div>



                <div class="field">

                    <label>
                        Category
                    </label>


                    <select name="product_category_id" required>

                        @foreach($categories as $category)

                            <option value="{{ $category->id }}"
                                @selected(old('product_category_id',$product->product_category_id)==$category->id)>

                                {{ $category->name }}

                            </option>

                        @endforeach

                    </select>

                </div>



                <div class="field full">

                    <label>
                        Product Name
                    </label>


                    <input
                        type="text"
                        name="name"
                        value="{{ old('name',$product->name) }}"
                        required>

                </div>



                <div class="field">

                    <label>
                        Product Code
                    </label>


                    <input
                        type="text"
                        name="code"
                        value="{{ old('code',$product->code) }}"
                        required>

                </div>



                <div class="field full">

                    <label>
                        Description
                    </label>


                    <textarea name="description">{{ old('description',$product->description) }}</textarea>

                </div>




                <div class="field full">

                    <label>
                        Outlet Availability
                    </label>


                    <div class="outlet-grid">


                        @foreach($outlets as $outlet)


                            <label class="outlet-card">


                                <input
                                    type="checkbox"
                                    name="outlet_ids[]"
                                    value="{{ $outlet->id }}"

                                    @checked(
                                        $product->outlets->contains('id',$outlet->id)
                                    )
                                >


                                <div>

                                    <div class="outlet-name">
                                        {{ $outlet->name }}
                                    </div>


                                    <div class="outlet-desc">
                                        Active Outlet
                                    </div>

                                </div>


                            </label>


                        @endforeach


                    </div>


                </div>



                <div class="field">

                    <label>
                        Status
                    </label>


                    <select name="is_active" required>

                        <option value="1"
                            @selected(old('is_active',(string)$product->is_active)=='1')>
                            Active
                        </option>


                        <option value="0"
                            @selected(old('is_active',(string)$product->is_active)=='0')>
                            Inactive
                        </option>


                    </select>

                </div>


            </div>



            <div class="actions">


                <button type="submit"
                        class="btn btn-primary">

                    Update Product

                </button>


                <a href="{{ route('backoffice.products.index') }}"
                   class="btn btn-dark">

                    Batal

                </a>


            </div>


        </form>


    </div>


</div>


@endsection
