<?php
namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Bank;
use App\Menu;
use App\FalseData;
use App\Market;
use App\Setting;
use App\HistoricalData;
use App\Users;
use App\Utils\RPC;
use App\Service\OSS;

class DefaultController extends Controller
{

    public function falseData()
    {
        $limit = Input::get('limit', '12');
        $page = Input::get('page', '1');
        
        $old = date("Y-m-d", strtotime("-1 day"));
        $old_time = strtotime($old);
        $time = strtotime(date("Y-m-d"));
        
        $yesterday = FalseData::where('time', ">", $old_time)->where("time", "<", $time)->sum('price');
        $today = FalseData::where('time', ">", $time)->sum('price');
        
        $data = FalseData::orderBy('id', 'DESC')->paginate($limit);
        
        return $this->success(array(
            "data" => $data->items(),
            "limit" => $limit,
            "page" => $page,
            "yesterday" => $yesterday,
            "today" => $today
        ));
    }

    public function quotation()
    {
        $result = Market::limit(20)->get();
        return $this->success(array(
            "coin_list" => $result
        ));
    }

    public function historicalData()
    {
        $day = HistoricalData::where("type", "day")->orderBy('id', 'asc')->get();
        $week = HistoricalData::where("type", "week")->orderBy('id', 'asc')->get();
        $month = HistoricalData::where("type", "month")->orderBy('id', 'asc')->get();
        
        return $this->success(array(
            "day" => $day,
            "week" => $week,
            "month" => $month
        ));
    }

    public function quotationInfo()
    {
        $id = Input::get("id");
        if (empty($id))
            return $this->error("参数错误");
        
        // $coin_list = RPC::apihttp("https://api.coinmarketcap.com/v2/ticker/".$id."/");
        $coin_list = Market::find($id);
        
        // $coin_list = @json_decode($coin_list,true);
        
        return $this->success($coin_list);
    }

    public function dataGraph()
    {
        $data = Setting::getValueByKey("chart_data");
        if (empty($data))
            return $this->error("暂无数据");
        
        $data = json_decode($data, true);
        return $this->success(array(
            "data" => array(
                $data["time_one"],
                $data["time_two"],
                $data["time_three"],
                $data["time_four"],
                $data["time_five"],
                $data["time_six"],
                $data["time_seven"]
            ),
            "value" => array(
                $data["price_one"],
                $data["price_two"],
                $data["price_three"],
                $data["price_four"],
                $data["price_five"],
                $data["price_six"],
                $data["price_seven"]
            ),
            "all_data" => $data
        ));
    }

    public function index()
    {
        $coin_list = RPC::apihttp("https://api.coinmarketcap.com/v2/ticker?limit=10");
        $coin_list = @json_decode($coin_list, true);
        
        if (! empty($coin_list["data"])) {
            foreach ($coin_list["data"] as &$d) {
                if ($d["total_supply"] > 10000) {
                    $d["total_supply"] = substr($d["total_supply"], 0, - 4) . "万";
                }
            }
        }
        return $this->success(array(
            "coin_list" => $coin_list["data"]
        ));
    }
    
    public function clientUpload(Request $request){
        if ($request->hasFile('file')) {
            $imageServerUrl = Setting::getBucketByKey('image_server_url', '');
            // 验证上传的文件
            $validated = $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            ]);
            // 获取表单提交的图片
            $file = $request->file('file');
            // 判断图片有效性
            if (!$file->isValid()) {
               return back()->withErrors('fail');
            }
            // 获取图片在临时文件中的地址
            $file = $file->getRealPath();
            // 制作文件名
            $key = 'client/' . date('Ymd') .'/' . date('YmdHis') . rand(10000000, 99999999) . '.jpg';
            //阿里 OSS 图片上传
            $result = OSS::upload($imageServerUrl, $key, $file);
            if ($result) {
               return $this->success($key);
            } else {
               return $this->error('fail');
            }
            
        }
        return $this->error('fail');
    }
    
    public function manageUpload(Request $request){
        if ($request->hasFile('file')) {
            $imageServerUrl = Setting::getBucketByKey('image_server_url', '');
            // 验证上传的文件
            $validated = $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);
            // 获取表单提交的图片
            $file = $request->file('file');
            // 判断图片有效性
            if (!$file->isValid()) {
               return back()->withErrors('fail');
            }
            // 获取图片在临时文件中的地址
            $file = $file->getRealPath();
            // 制作文件名
            $key = 'manage/' . date('Ymd') .'/' . date('YmdHis') . rand(10000000, 99999999) . '.jpg';
            //阿里 OSS 图片上传
            $result = OSS::upload($imageServerUrl, $key, $file);
            if ($result) {
               return $this->success(OSS::getObjectURL($imageServerUrl, $key));
            } else {
               return $this->error('fail');
            }
            
        }
        return $this->error('fail');
    }

    public function getNode(\Illuminate\Http\Request $request)
    {
        $user_id = $request->get('user_id', 0);
        $show_message["real_teamnumber"] = Users::find($user_id)->real_teamnumber;
        $show_message["top_upnumber"] = Users::find($user_id)->top_upnumber;
        $show_message["today_real_teamnumber"] = Users::find($user_id)->today_real_teamnumber;
        $account_number = $request->get('account_number', null);
        if (! empty($account_number)) {
            $user_id_search = Users::where('account_number', $account_number)->first();
            if (! empty($user_id_search)) {
                $user_id = $user_id_search->id;
            } else {
                $user_id = 0;
            }
        }
        // if (empty($user_id)){
        $users = Users::where('parent_id', $user_id)->get();
        $results = array();
        foreach ($users as $key => $user) {
            $results[$key]['name'] = $user->account_number;
            $results[$key]['id'] = $user->id;
            $results[$key]['parent_id'] = $user->parent_id;
        }
        $data["show_message"] = $show_message;
        $data["results"] = $results;
        return $this->success($data);
    }

    public function getVersion()
    {
        $version = Setting::getValueByKey('version', '1.0');
        return $this->success($version);
    }

    public function getBanks()
    {
        $result = Bank::all();
        return $this->success($result);
    }

    public function language(Request $request)
    {
        $lang = $request->get('lang', 'zh');
        session()->put('lang', $lang);
        return $this->success($lang);
    }
    
     public function getMenu()
    {
        $menu = Menu::where('show', 1)->orderBy('sort','asc')->get();
        return $this->success($menu);
    }

    public function getSiteConfig(Request $request) {
        $model = Setting::whereIn('key', ['site_name', 'site_logo','site_pc_logo', 'down_logo','open_url'
            ,'zxkf_radio','zxkf_url','telegram_url','telegram_radio','skype_radio','skype_url','whatsApp_radio'
            ,'whatsApp_url','line_radio','line_url','jie_radio','jie_url','hk_radio','hk_url','bank_flag','image_server_url','tk_radio','yzm_radio','ios_apk_download_url','apk_download_url'
        ])->get();
        $settings = [];
        foreach ($model as $setting) {
            if($setting->key == 'image_server_url'){
                $settings[$setting->key] = 'http://' . $setting->value . '.oss-cn-hongkong.aliyuncs.com/';
            }else{
                $settings[$setting->key] = $setting->value;
            }
        }
        return $this->success($settings);
    }
}
?>