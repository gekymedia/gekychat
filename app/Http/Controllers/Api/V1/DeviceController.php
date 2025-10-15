<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller {
    public function store(Request $r) {
        $r->validate(['platform'=>'required','fcm_token'=>'required','device_name'=>'nullable']);
        $d = Device::updateOrCreate(
            ['user_id'=>$r->user()->id, 'fcm_token'=>$r->fcm_token],
            ['platform'=>$r->platform,'device_name'=>$r->device_name]
        );
        return response()->json($d, 201);
    }
    public function destroy(Request $r, $id) {
        Device::where('id',$id)->where('user_id',$r->user()->id)->delete();
        return response()->json(['ok'=>true]);
    }
}
