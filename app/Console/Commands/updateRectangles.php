<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Responsitory\Business;

class updateRectangles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:rectangles';
    
    /**
     * The console command description.
     *
     * @var string
     */
    private $business;
    protected $description = 'Update Rectangles every 30 mins';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->business= new Business();
    }
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        while (1) {
            $this->business->toHistory();
            
            sleep(1800);
        }
    }
}
