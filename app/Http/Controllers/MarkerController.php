<?php

namespace App\Http\Controllers;

use App\Jobs\ReadFileUploaded;
use App\Jobs\TestLog;
use App\Responsitory\Business;
use App\Responsitory\History;
use App\Responsitory\Rectangles;
use Carbon\Carbon;
use Illuminate\Http\Request;


class MarkerController extends Controller
{
    private $business;
    
    function __construct()
    {
        $this->business = new Business();
    }
    
    function recalculateMap()
    {
        $this->business->addNewData(1, 15.5, 1, 3);
    }
    
    public function postUpload(Request $request)
    {
        if ($request->hasFile('fileCSV')) {
            $files = $request->file('fileCSV');
            foreach ($files as $file){
                $name = time() . '-' . $file->getClientOriginalName();
                //check out the edit content on bottom of my answer for details on $storage
                $path = public_path() . "\\uploads\\";
                // Moves file to folder on server
                $file->move($path, $name);
//            $fileCSV = fopen($path . $name, 'r') or die("Unable to open file!");
//            // Import the moved file to DB and return OK if there were rows affected
//
//            while (!feof($fileCSV)) {
//                $row = fgetcsv($fileCSV);
//                if ($row == "") {
//                    break;
//                }
//                $this->business->insertMarker($row);
//            }
                $job = new ReadFileUploaded($path, $name);
                dispatch($job);
                $job->delete();
            }
            
            return view('admin.pages.current');
        } else {
            return view('admin.pages.upload');
        }
    }
    
    public function showUploadForm()
    {
        return view('admin.pages.upload');
    }
    
    
    public function showColorXML($start_time = '')
    {
        if ($start_time == '') {
            $rectangles = $this->business->fetchRectangle();
        } elseif ($start_time == 'future') {
            $rectangles = $this->business->calculateFuture();
        } else {
            $rectangles = $this->business->readHistory($start_time);
        }
        return response()->view('admin.pages.color', compact('rectangles', 'start_time'))->header('Content-Type',
          'text/xml');
    }
    
    public function reset()
    {
        Rectangles::where('id', '>', 0)->update([
          'avg_speed' => 0,
          'marker_count' => 0,
          'color' => '#808080',
          'overwrite_user' => null
        ]);
//        $this->business->resetColors();
        return response()->view('admin.pages.current');
    }
    
    public function overwrite(Request $request)
    {
        $this->business->overwriteRectangle($request->whereX, $request->whereY, $request->color);
    }
    
    public function showHistory($start_time = '')
    {
        return view('admin.pages.history', compact('start_time'));
    }
    
    public function showCurrent()
    {
        return view('admin.pages.current');
    }
    
    public function showFuture()
    {
        return view('admin.pages.future');
    }
    
    public function saveToHistory()
    {
        $now = Carbon::now()->subMinutes(30);
        $rectangles = Rectangles::select('color')->get();
        $result = '';
        foreach ($rectangles as $rectangle) {
            $result .= $this->business->getLevelFromColor($rectangle->color);
        }
        $id = History::select('id')->where('end_time', '>', Carbon::now()->toDateTimeString())->first();
        if (!isset($id)) {
            History::insert(
              [
                'start_time' => $now->toDateTimeString(),
                'end_time' => $now->addMinutes(30)->toDateTimeString(),
                'colors' => $result
              ]
            );
            Rectangles::where('id', '>', 0)->update([
              'avg_speed' => 0,
              'marker_count' => 0,
              'color' => '#808080',
              'overwrite_user' => null
            ]);
            return view('admin.pages.current');
        } else {
            return view('admin.pages.current')->withErrors('Có lỗi xảy ra, vui lòng thử lại');
        }
    }
    
    public function index()
    {
        $this->dispatch(new TestLog());
    }
}
