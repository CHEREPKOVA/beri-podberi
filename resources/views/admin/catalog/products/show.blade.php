@extends('layouts.app')

@section('title', 'Карточка товара')
@section('heading', 'Карточка товара')

@section('content')
    @include('admin.partials.flash')
    @include('catalog.product._show_content')
@endsection
