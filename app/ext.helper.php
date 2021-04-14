<?php



if (!function_exists('id_mix')) {
    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param  array  $array
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    function id_mix($id, $key = 'id')
    {
        if (!$id || !is_array($id) || !isset($id[$key])) return $id;
        return $id[$key];
    }
}
