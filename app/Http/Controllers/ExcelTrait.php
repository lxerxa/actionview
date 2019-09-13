<?php

namespace App\Http\Controllers;

trait ExcelTrait
{
    /**
     * remove the empty row and trim the cell value
     *
     * @param  array $data
     * @return array
     */
    public function trimExcel($data)
    {
        if (!is_array($data))
        {
            return [];
        }

        // trim the cell value
        foreach($data as $k1 => $val)
        {
            foreach($val as $k2 => $val2)
            {
                $data[$k1][$k2] = trim($val2);
            }
        }

        // delete the empty row
        $data = array_filter($data, function($v) { return array_filter($v); });
        // delete the empty column
        $data = array_filter($this->rotate($data), function($v) { return array_filter($v); });
        // rotate the array
        $data = $this->rotate($data);

        return $data;
    }

    /**
     * arrange the data 
     *
     * @param  array $data
     * @param  array $fields
     * @return array
     */
    public function arrangeExcel($data, $fields)
    {
        $data = $this->trimExcel($data);

        $header_index = 0;
        while(true)
        {
            $header = array_shift($data);
            if (!$header)
            {
                throw new \UnexpectedValueException('表头定位错误。', -11142);
            }

            if (is_array($header))
            {
                if (in_array($header[0], $fields))
                {
                    break;
                }
                if (++$header_index > 5)
                {
                    throw new \UnexpectedValueException('表头定位错误。', -11142);
                }
            }
        }

        // the first row is used for the issue keys
        if (array_search('', $header) !== false)
        {
            throw new \UnexpectedValueException('表头不能有空值。', -11143);
        }
        // check the header title
        if (count($header) !== count(array_unique($header)))
        {
            throw new \UnexpectedValueException('表头不能有重复列。', -11144);
        }

        $field_keys = [];
        foreach($header as $field)
        {
            $tmp = array_search($field, $fields);
            if ($tmp === false)
            {
                throw new \UnexpectedValueException('表头有不明确列。', -11145);
            }
            $field_keys[] = $tmp;
        }

        $new_data = [];
        foreach ($data as $item)
        {
            $tmp = [];
            foreach ($item as $key => $val)
            {
                $tmp[$field_keys[$key]] = $val;
            }
            $new_data[] = $tmp;
        }

        return $new_data;
    }

    /**
     * rotate the matrix 
     *
     * @param  array $matrix
     * @return array
     */
    public function rotate(array $matrix)
    {
        $ret = [];
        foreach($matrix as $val)
        {
            foreach($val as $k => $val2)
            {
                $ret[$k][] = $val2;
            }
        }
        return $ret;
    }
}
