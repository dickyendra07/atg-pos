<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary destructive cleanup delete
    |--------------------------------------------------------------------------
    |
    | While ATG is still in its testing / data-cleanup phase, owner and admin pusat may remove
    | Product, Variant, Ingredient (tombstone) and Recipe (real delete) from the Back Office.
    | OFF unless explicitly enabled; business code must read config(), never env().
    |
    */

    'destructive_delete_enabled' => (bool) env('BACKOFFICE_DESTRUCTIVE_DELETE_ENABLED', false),

];
