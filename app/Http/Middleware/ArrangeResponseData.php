<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Closure;

use MongoDB\BSON\ObjectID;

class ArrangeResponseData 
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse)
        {
            $old_data = $response->getData(true);
            $new_data = $this->arrange($old_data);
            $response->setData($new_data);
        }
        return $response;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function arrange($data)
    {
        if (!is_array($data))
        {
            return $data;
        }

        if (array_key_exists('_id', $data))
        {
            $data['id'] = $data['_id'] instanceof ObjectID ? $data['_id']->__toString() : $data['_id'];
            unset($data['_id']);
        }
        foreach ($data as $k => $val)
        {
            $data[$k] = $this->arrange($val);
        }

        return $data;
    }

}
