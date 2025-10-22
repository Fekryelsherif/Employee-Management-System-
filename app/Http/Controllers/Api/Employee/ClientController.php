<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Service;
use App\Models\City;

class ClientController extends Controller
{
    // GET all clients
    public function index() {
        return Client::with(['services','city'])->get();
    }

    // POST create client
    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string|max:20',
            'national_id' => 'nullable|string|max:50',
            'address' => 'required|string',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
            'city_id' => 'required|exists:cities,id',
            'services' => 'array',
            'services.*' => 'exists:services,id',
            'created_by' => 'required|exists:users,id',
        ]);

        $client = Client::create($data);

        // dd($request->services);

        if (isset($data['services'])) {
        $client->services()->sync($data['services']);
      }

// رجّع الـ client مع كل العلاقات
      return response()->json($client->load('services', 'city'), 201);
    }

    // GET single client
    public function show($id) {
        $client = Client::with(['services','city'])->findOrFail($id);
        return response()->json($client);
    }

    // PUT update client
    public function update(Request $request, $id) {
        $client = Client::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:clients,email,'.$id,
            'phone' => 'sometimes|string|max:20',
            'national_id' => 'nullable|string|max:50',
            'address' => 'sometimes|string',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
            'city_id' => 'sometimes|exists:cities,id',
            'services' => 'array',
            'services.*' => 'exists:services,id',
            'created_by' => 'sometimes|exists:users,id',
        ]);

        $client->update($data);

        if(isset($data['services'])) {
            $client->services()->sync($data['services']);
        }

        return response()->json($client->load('services','city'));
    }

    // DELETE client
    public function destroy($id) {
        $client = Client::findOrFail($id);
        $client->services()->detach();
        $client->delete();

        return response()->json(['message'=>'Client deleted successfully']);
    }





//_____________________________________***_____________________________________






// Functions of Clients & Services
//   1. إضافة ربط بين عملاء وخدمات
    public function storeClientService(Request $request)
    {
        $data = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id',
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,id',

        ]);

        foreach ($data['client_ids'] as $clientId) {
            $client = Client::find($clientId);
            $client->services()->syncWithoutDetaching($data['service_ids']);
        }

        return response()->json(['message' => 'تم ربط العملاء بالخدمات بنجاح']);
    }

    //   2. عرض كل العملاء مع الخدمات المرتبطة بيهم
    public function indexOfClientService()
    {
        $clients = Client::with('services:id,name')->get(['id', 'name']);
        return response()->json($clients);
    }


     //   عرض كل العملاء مع الخدمات المرتبطة بيهم
    public function indexClients()
    {
        $clients = Client::with('services:id,name')->get(['id', 'name']);
        return response()->json($clients);
    }

    //   عرض كل الخدمات مع العملاء المرتبطين بيها
    public function indexServices()
    {
        $services = Service::with('clients:id,name')->get(['id', 'name']);
        return response()->json($services);
    }

    //   تعديل الخدمات المرتبطة بعميل معيّن
    public function updateClientServices(Request $request, $clientId)
    {
        $data = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,id',
        ]);

        $client = Client::findOrFail($clientId);
        $client->services()->sync($data['service_ids']);

        return response()->json([
            'message' => 'تم تحديث الخدمات الخاصة بالعميل بنجاح',
            'client' => $client->load('services:id,name'),
        ]);
    }

    //   تعديل العملاء المرتبطين بخدمة معيّنة
    public function updateServiceClients(Request $request, $serviceId)
    {
        $data = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id',
        ]);

        $service = Service::findOrFail($serviceId);
        $service->clients()->sync($data['client_ids']);

        return response()->json([
            'message' => 'تم تحديث العملاء المرتبطين بالخدمة بنجاح',
            'service' => $service->load('clients:id,name'),
        ]);
    }

    //   حذف العلاقة بين عميل وخدمة (لو عايز Function Delete)
    public function detachServiceFromClient($clientId, $serviceId)
    {
        $client = Client::findOrFail($clientId);
        $client->services()->detach($serviceId);

        return response()->json([
            'message' => 'تم إزالة الخدمة من العميل بنجاح',
        ]);
    }






//_____________________________________***_____________________________________






// profile functions

  public function profile(Request $request)
{
    $user = $request->user()->makeHidden(['email_verified_at']);
    return response()->json($user);
}

/**
 *  تحديث بيانات المستخدم الحالي
 */
public function updateProfile(Request $request)
{
    $user = $request->user();

    //   تحقق من البيانات الجديدة
    $data = $request->validate([
        'fname' => 'sometimes|string|max:50',
        'lname' => 'sometimes|string|max:50',
        'email' => 'sometimes|email|unique:users,email,' . $user->id,
        'password' => 'sometimes|min:6|confirmed',
    ]);

    //   تحديث البيانات
    if (isset($data['password'])) {
        $data['password'] = bcrypt($data['password']);
    }

    $user->update($data);

    return response()->json([
        'message' => 'تم تحديث الملف الشخصي بنجاح',
        'user' => $user->makeHidden(['email_verified_at']),
    ]);
}

/**
 *  حذف حساب المستخدم الحالي
 */
public function deleteProfile(Request $request)
{
    $user = $request->user();

    $user->tokens()->delete(); // حذف التوكنز الخاصة بالمستخدم
    $user->delete(); // حذف المستخدم نفسه

    return response()->json([
        'message' => 'تم حذف الحساب بنجاح'
    ]);
}
}
