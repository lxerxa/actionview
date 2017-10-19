<?php

namespace App\Project\Eloquent;

use Jenssegers\Mongodb\Eloquent\Model;

class BoardRankMap extends Model
{
    //
    protected $table = 'board_rank_map';

    protected $fillable = array(
        'board_id',
        'col_no',
        'parent',
        'rank'
    );
}
