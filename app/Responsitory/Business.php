<?php

namespace App\Responsitory;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * contain all Business logic
 * Class Business
 * @package App\Responsitory
 */
define("north", 21.157200);
define("east", 105.919876);
define("south", 20.951180);
define("west", 105.456390);
define("height", 23);
define("width", 56);

class Business // extends Model
{
    private $rectangles;
    private $markers;
    private $history;
    
    public function findRectangleInclude($lat, $lng)
    {
        $whereX = floor(($lat - south) / (north - south) * height);
        $whereY = floor(($lng - west) / (east - west) * width);
        if ($whereX == height) {
            $whereX--;
        } elseif ($whereX > height || $whereX < 0) {
            return false;
        }
        if ($whereY == width) {
            $whereY--;
        } elseif ($whereY > width || $whereY < 0) {
            return false;
        }
        return ['whereX' => $whereX, 'whereY' => $whereY];
    }
    
    public function __construct()
    {
        $this->rectangles = new Rectangles();
        $this->markers = new Markers();
        $this->history = new History();
    }
    
    /**
     * @param $avg_speed
     * @return string
     *
     * calculate the color from avg_speed
     */
    public function getColor($avg_speed)
    {
        if ($avg_speed > 30) {
            return '#0000FF';
        } elseif ($avg_speed > 20) {
            return '#00FF00';
        } elseif ($avg_speed > 10) {
            return '#FFFF00';
        } else {
            return '#FF0000';
        }
    }
    
    /**
     * @param $position
     * @param $avg_speed
     * @param $marker_count
     *
     * input an user's data in 1 rectangle
     * recalculate the rectangle's attribute
     */
    public function addNewData($whereX, $whereY, $avg_speed, $marker_count)
    {
        $rectangle = $this->rectangles->select('id', 'avg_speed', 'marker_count')->where([
          ['height', '=', $whereX],
          ['width', '=', $whereY],
        ])->first();
        $rectangle->avg_speed = ($rectangle->avg_speed * $rectangle->marker_count + $avg_speed * $marker_count) / ($rectangle->marker_count + $marker_count);
        $rectangle->marker_count = $rectangle->marker_count + $marker_count;
        $rectangle->color = $this->getColor($rectangle->avg_speed);
        $rectangle->save();
    }
    
    /**
     * @param $arr_id
     * @param $color
     *
     * overwrite the rectangle's color
     */
    public function overwriteRectangle($whereX, $whereY, $color)
    {
        $rectangle = $this->rectangles->select('id', 'color', 'overwrite_user')->where([
          ['height', '=', $whereX],
          ['width', '=', $whereY],
        ])->first();
        $rectangle->color = $color;
        $rectangle->overwrite_user = Auth::user()->id;
        $rectangle->save();
    }
    
    public function insertMarker($row)
    {
        $position = $this->findRectangleInclude($row[ 0 ], $row[ 1 ]);
        $this->addNewData($position[ 'whereX' ], $position[ 'whereY' ], $row[ 2 ], 1);
    }
    
    
    public function csvToArray($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }
        
        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $data[] = array_combine(['lat', 'lng', 'speed', 'record_time'], $row);
            }
            fclose($handle);
        }
        dd($data);
        return $data;
    }
    
    public function fetchRectangle()
    {
        return $this->rectangles->select('id', 'height', 'width', 'color', 'avg_speed', 'marker_count')->get();
    }
    
    /**
     * @param UploadedFile $img
     * @param string $path
     * @return null|string Ten file img upload
     */
    public function saveImg($img, $path)
    {
        if ($img == null) {
            return null;
        }
        $err = null;
        $name = $img->getClientOriginalName();
        $ext = $img->getClientOriginalExtension();
        //kiem tra file trung ten
        while (file_exists($path . '/' . $name)) {
            $name = str_random(5) . "_" . $name;
        }
        $arr_ext = ['png', 'jpg', 'gif', 'jpeg'];
        if (!in_array($ext, $arr_ext) || $img->getClientSize() > 500000) {
            $name = null;
            return redirect()->back()->with('not_img', 'Chọn file ảnh png jpg gif jpeg có kích thước < 5Mb');
        } else {
            $img->move($path, $name);
        }
        return $name;
    }
    
    public function getColorFromLevel($level)
    {
        if ($level == '1') {
            return '#FF0000';
        }
        if ($level == '2') {
            return '#FFFF00';
        }
        if ($level == '3') {
            return '#00FF00';
        }
        if ($level == '4') {
            return '#0000FF';
        }
        return '#808080';
    }
    
    public function getOriginColor($speed)
    {
        if ($speed <= 10) {
            return '#FF0000';
        }
        if ($speed <= 20) {
            return '#FFFF00';
        }
        if ($speed <= 30) {
            return '#00FF00';
        }
        return '#0000FF';
    }
    
    public function getLevelFromColor($color)
    {
        if ($color == "#FF0000") {
            return 1;
        }
        if ($color == "#FFFF00") {
            return 2;
        }
        if ($color == "#00FF00") {
            return 3;
        }
        if ($color == "#0000FF") {
            return 4;
        }
        return 0;
    }
    
    public function resetColors()
    {
        $rectangles = $this->fetchRectangle();
        foreach ($rectangles as $rectangle) {
            if ($rectangle->marker_count == 0) {
                $rectangle->color = '#808080';
            } else {
                $rectangle->color = $this->getOriginColor($rectangle->avg_speed);
            }
            $rectangle->save();
        }
    }
    
    public function readHistory($start_time)
    {
        $colors = $this->history->where([
          ['start_time', '<=', $start_time],
          ['end_time', '>', $start_time]
        ])->first()->colors;
        $rectangles = $this->rectangles->select('height', 'width', 'color')->get();
        for ($i = 0; $i < height * width; $i++) {
            $rectangles[ $i ]->color = $this->getColorFromLevel($colors[ $i ]);
        }
        return $rectangles;
    }
    
    private function isHoliday($date)
    {
        if ($date->isWeekend() ||
          ($date->day == '1' && $date->month == '1') ||
          ($date->day == '8' && $date->month == '3') ||
          ($date->day == '30' && $date->month == '4') ||
          ($date->day == '1' && $date->month == '5') ||
          ($date->day == '1' && $date->month == '6') ||
          ($date->day == '2' && $date->month == '9') ||
          ($date->day == '20' && $date->month == '10') ||
          ($date->day == '20' && $date->month == '11') ||
          ($date->day == '23' && $date->month == '12') ||
          ($date->day == '31' && $date->month == '12')
        ) {
            return true;
        } else {
            return false;
        }
    }
    
    public function calculateFuture()
    {
        $last_time = Carbon::now('Asia/Ho_Chi_Minh')->subMinutes(30)->toDateTimeString();
        if ($this->isHoliday(Carbon::now('Asia/Ho_Chi_Minh')->subDays(7))) {
            $weekly = Carbon::now('Asia/Ho_Chi_Minh')->subDays(14)->toDateTimeString();
        } else {
            $weekly = Carbon::now('Asia/Ho_Chi_Minh')->subDays(7)->toDateTimeString();
        }
        $data_lastest = $this->history->where([
          ['start_time', '<=', $last_time],
          ['end_time', '>', $last_time]
        ])->first()->colors;
        $data_weekly = $this->history->where([
          ['start_time', '<=', $weekly],
          ['end_time', '>', $weekly]
        ])->first()->colors;
        $rectangles = $this->rectangles->select('height', 'width', 'color')->get();
        if ($this->isHoliday(Carbon::now('Asia/Ho_Chi_Minh'))) {
            for ($i = 0; $i < height * width; $i++) {
                
                $rectangles[ $i ]->color =
                  $this->getColorFromLevel(round($data_lastest[ $i ] * 0.7 + $data_weekly[ $i ] * 0.3));
                $rectangles[ $i ]->lastest_data = $data_lastest[ $i ];
                $rectangles[ $i ]->weekly_data = $data_weekly[ $i ];
            }
            return $rectangles;
        } else {
            for ($i = 0; $i < height * width; $i++) {
                $rectangles[ $i ]->color =
                  $this->getColorFromLevel(round($data_lastest[ $i ] * 0.4 + $data_weekly[ $i ] * 0.6));
                $rectangles[ $i ]->lastest_data = $data_lastest[ $i ];
                $rectangles[ $i ]->weekly_data = $data_weekly[ $i ];
            }
            return $rectangles;
        }
    }
    
    public function toHistory()
    {
    
    }
}