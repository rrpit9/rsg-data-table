<?php
if (! function_exists('studly_case')) {
    /*Convert a value to studly caps case.*/
    function studly_case($value)
    {
        return Illuminate\Support\Str::studly($value);
    }
}

if (! function_exists('str_singular')) {
    /*Get the singular form of an English word.*/
    function str_singular($value)
    {
        return Illuminate\Support\Str::singular($value);
    }
}