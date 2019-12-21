<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\System\Eloquent\CalendarSingular;

use App\Utils\Lunar;
use App\Utils\CurlRequest;

class CalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('privilege:sys_admin');
        parent::__construct();
    }

    var $solar_special_days = [
        '0101' => '元旦',
        '0501' => '劳动',
        '1001' => '国庆',
    ];

    var $lunar_special_days = [
        '0101' => '春节',
        '0505' => '端午',
        '0815' => '中秋',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $year)
    {
    	if ($year == 'current')
        {
    	    $year = date('Y');
        }
        if ($year > 2038 || $year < 1970)
        {
    	    throw new \UnexpectedValueException('the assigned year has error.', -16020);
        }

    	$dates = $this->getYearDates(intval($year));

        return Response()->json([ 'ecode' => 0, 'data' => $dates, 'options' => [ 'year' => date('Y'), 'date' => date('Y/m/d') ] ]);
    }

    /**
     * fetch the year dates .
     *
     * @param number $year
     * @return array
     */
    public function getYearDates($year)
    {
        $year_singulars = [];
        $singulars = CalendarSingular::where('year', $year)->get();
        foreach ($singulars as $val)
        {
            $year_singulars[$val->date] = $val;
        }

        $dates = $this->getYearBasicDates($year);
        foreach ($dates as $key => $date)
        {
            if (!isset($year_singulars[$date['date']]))
            {
                continue;
            }

            $singular_date = $year_singulars[$date['date']];
            if (isset($singular_date['type']) && $singular_date['type'])
            {
                $dates[$key]['type'] = $singular_date['type'];
            }

            if (isset($singular_date['target']) && $singular_date['target'])
            {
                $dates[$key]['target'] = $singular_date['target'];
            }
        }
        return $dates;
    }

    /**
     * convert the solar to lunar.
     *
     * @param number $year
     * @param number $month
     * @param number $day
     * @return array
     */
    private function convert2lunar($year, $month, $day)
    {
        $lunarObj = new Lunar();
        $lunar_info = $lunarObj->convertSolarToLunar($year, $month, $day);

        $lunar_date = sprintf('%02d', $lunar_info[4]) . sprintf('%02d', $lunar_info[5]);

        return [
            'year' => $lunar_info[3], 
            'month' => $lunar_info[1], 
            'day' => $lunar_info[2],
            'target' => isset($this->lunar_special_days[$lunar_date]) ? $this->lunar_special_days[$lunar_date] : '',
        ];
    }

    /**
     * get the whole year basic dates.
     *
     * @param string $year
     * @return array
     */
    private function getYearBasicDates($year)
    {
        $dates = [];
        for ($i = 1; $i <= 12; $i++)
        {
            $mcnt = date('t', strtotime($year . '-' . $i . '-1'));
            for ($j = 1; $j <= $mcnt; $j++)
            {
                $lunar = $this->convert2lunar($year, $i, $j);

                $solar_date = sprintf('%02d', $i) . sprintf('%02d', $j);
                $w = intval(date('w', strtotime($year . '-' . $i . '-' . $j)));

                $dates[] = [
                    'date' => $year . '/' . sprintf('%02d', $i) . '/'. sprintf('%02d', $j),
                    'year' => $year,
                    'month' => $i,
                    'day' => $j,
                    'week' => $w == 0 ? 7 : $w,
                    'target' => isset($this->solar_special_days[$solar_date]) ? $this->solar_special_days[$solar_date] : '',
                    'lunar' => $lunar,
                ];
            }
        }
        return $dates;
    }

    public function update(Request $request)
    {
        $start_date = $request->input('start_date');
        if (!isset($start_date) || !$start_date)
        {
            throw new \UnexpectedValueException('the start date can not be empty.', -16021);
        }

        $end_date = $request->input('end_date');
        if (!isset($end_date) || !$end_date)
        {
            throw new \UnexpectedValueException('the end date can not be empty.', -16022);
        }

        $dates = [];
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        for ($i = $start_time; $i <= $end_time; $i = $i + 3600 * 24)
        {
            $dates[] = date('Y/m/d', $i);
        }
        if (!$dates)
        {
            throw new \UnexpectedValueException('the date range can not be empty.', -16023);
        }

        $mode = $request->input('mode');
        if (!isset($mode) || !$mode)
        {
            throw new \UnexpectedValueException('the operate mode can not be empty.', -16024);
        }

        if ($mode === 'set')
        {
            $type = $request->input('type');
            if (!isset($type) || !$type)
            {
                throw new \UnexpectedValueException('the setted type can not be empty.', -16025);
            }
            if (!in_array($type, [ 'holiday', 'workday' ]))
            {
                throw new \UnexpectedValueException('the setted type has error.', -16026);
            }
        }

        CalendarSingular::whereIn('date', $dates)->delete();

        if ($mode === 'set')
        {
            foreach ($dates as $date)
            {
                CalendarSingular::create([
                    'date' => $date,
                    'year' => intval(substr($date, 0, 4)),
                    'type' => $type
                ]);
            }
        }

        return Response()->json([ 'ecode' => 0, 'data' => $this->getYearDates(intval(substr($start_date, 0, 4)))]);
    }

    /**
     * sync the year singular calendars.
     *
     * @param string $year
     * @@return array
     */
    public function sync(Request $request)
    {
        $year = $request->input('year');
        if (!isset($year) || !$year)
        {
            throw new \UnexpectedValueException('the sync year can not be empty.', -16027);
        }

        $year = intval($year);

        $url = 'http://www.actionview.cn:8080/api/holiday/' . $year;

        $res = CurlRequest::get($url);
        if (!isset($res['ecode']) || $res['ecode'] != 0)
        {
            throw new \UnexpectedValueException('failed to request the api.', -16028);
        }

        if (!isset($res['data']) || !$res['data'])
        {
            throw new \UnexpectedValueException('the sync year data is empty.', -16029);
        }

        CalendarSingular::where('year', $year)->delete();

        $singulars = $res['data'];
        foreach ($singulars as $value) 
        {
            CalendarSingular::create([
                'date' => $value['date'],
                'year' => $year,
                'type' => isset($value['holiday']) && $value['holiday'] ? 'holiday' : 'workday'
                ]);   
        }

        return Response()->json([ 'ecode' => 0, 'data' => $this->getYearDates($year) ]);
    }
}
