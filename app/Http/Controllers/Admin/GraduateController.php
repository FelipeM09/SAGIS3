<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Repositories\CityRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Repositories\PersonRepository;
use App\Repositories\ProgramRepository;
use Illuminate\Support\Facades\Storage;
use App\Repositories\DocumentTypeRepository;
use App\Http\Requests\Graduates\StoreRequest;
use App\Repositories\PersonAcademicRepository;
use App\Http\Requests\Graduates\UpdatePasswordRequest;

class GraduateController extends Controller
{
    /**
     * @var UserRepository
     */
    protected $userRepository;

    /** @var PersonRepository */
    protected $personRepository;

    /** @var RoleRepository */
    protected $roleRepository;

    /** @var DocumentTypeRepository */
    protected $documentTypeRepository;

    /** @var CityRepository */
    protected $cityRepository;

     /** @var PersonAcademicRepository */
     protected $personAcademicRepository;

       /** @var ProgramRepository */
       protected $programRepository;

    /** @var \Spatie\Permission\Models\Role */
    protected $role;

    public function __construct(
        UserRepository $userRepository,
        PersonRepository $personRepository,
        RoleRepository $roleRepository,
        DocumentTypeRepository $documentTypeRepository,
        CityRepository $cityRepository,
        ProgramRepository $programRepository,
        PersonAcademicRepository $personAcademicRepository

    ) {
        $this->middleware('auth:admin');

        $this->userRepository = $userRepository;
        $this->personRepository = $personRepository;
        $this->roleRepository = $roleRepository;
        $this->documentTypeRepository = $documentTypeRepository;
        $this->cityRepository = $cityRepository;
        $this->programRepository = $programRepository;
        $this->personAcademicRepository = $personAcademicRepository;

        $this->role = $this->roleRepository->getByAttribute('name', 'graduate');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $items = $this->userRepository->getByRole($this->role->name);

            return view('admin.pages.graduates.index', compact('items'));
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            $documentTypes = $this->documentTypeRepository->all();
            $cities = $this->cityRepository->allOrderBy('countries.id');
           // $programs = $this->progmamRepository->getByAttribute('level_id', 1);

            // return $cities;

            return view('admin.pages.graduates.create', compact('documentTypes', 'cities'));
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        try {

            DB::beginTransaction();

           // dd($request->file('image'));
        
            if(!($request->file('image') == null)) {
                         /** Saving Photo */
                         $fileParams = $this->saveImage($request);
            }
   

            /** Creating Person */
            $personParams = $request->except(['code', 'company_email', 'image', '_token']);
            //$personParams = array_merge($personParams, $fileParams);

            if(!($request->file('image') == null)) {
                $personParams = array_merge($personParams,  $fileParams);
            }else{
                $personParams = array_merge($personParams);
            }
           

            $this->personRepository->create($personParams);

            /** Searching created Person */
            $person = $this->personRepository->getByAttribute('email', $request->email);

            /** Creating User */
            $userParams = $request->only(['code', 'company_email']);

            $userParams['email'] = $userParams['company_email'];
            $userParams['person_id'] = $person->id;
            $userParams['password'] = 'password';
      

            unset($userParams['company_email']);

            $this->userRepository->create($userParams);

            /**Creating PersonAcademic */
            $personAcademicParams = $request->only( ['person_id', 'program_id', 'year']);
            $personAcademicParams['person_id'] = $person->id;

           // $pregrade = $this->programRepository->getByAttribute('level_id', 1);
           // $programs = $this->progmamRepository->getByAttribute('name', "Programa de Ingenier??a de Sistema");
           $programs = $this->programRepository->first()->id;

           // dd($programs);
            $personAcademicParams['program_id'] = $programs;
            $personAcademicParams['year'] = 0;

            $this->personAcademicRepository->create($personAcademicParams);


            /** Searching User */
            $user = $this->userRepository->getByAttribute('email', $userParams['email']);

            $user->roles()->attach($this->role);

            DB::commit();
            return redirect()->route('admin.graduates.index')->with('alert', ['title' => '????xito!', 'icon' => 'success', 'message' => 'Se ha registrado correctamente.']);

           // return back()->with('alert', ['title' => '????xito!', 'icon' => 'success', 'message' => 'Se ha registrado correctamente.']);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return back()->with('alert', ['title' => '??Error!', 'icon' => 'error', 'message' => 'Se ha registrado correctamente.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $item = $this->personRepository->getById($id);

            $academics = $item->personAcademic;

            $laborales = $item->personCompany;

            return view('admin.pages.graduates.show', compact('item', 'academics', 'laborales'));
        } catch (\Exception $th) {
            throw $th->getMessage();
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $item = $this->personRepository->getById($id);

            $documentTypes = $this->documentTypeRepository->all();
            $cities = $this->cityRepository->allOrderBy('countries.id');

            return view('admin.pages.graduates.edit', compact('item', 'documentTypes', 'cities'));
        } catch (\Exception $th) {
            throw $th->getMessage();
        }
    }

    /**
     * Show the form for editing the Graduate's password.
     * 
     * @param int $id
     * 
     * @return \Illuminate\Http\Response
     */
    public function edit_password($id)
    {
        try {
            $item = $this->personRepository->getById($id);

            return view('admin.pages.graduates.edit_password', compact('item'));
        } catch (\Exception $th) {
            //throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * @param UpdatePasswordRequest $request
     * @param int $id
     * 
     * @return \Illuminate\Http\Response
     */
    public function update_password(UpdatePasswordRequest $request, $id)
    {
        try {
            $params = $request->all();

          //  dd($params);
          $item = $this->personRepository->getById($id);

          //dd($item->user);
           // $item = $this->userRepository->getById($id);
            //dd($item);

            $this->userRepository->update($item->user, $params);

            return  redirect()->route('admin.graduates.index')->with('alert', [
                'title' => '????xito!',
                'icon' => 'success',
                'message' => 'Se ha actualizado correctamente la contrase??a'
            ]);
        } catch (\Exception $th) {
            dd($th);
            return back()->with('alert', [
                'title' => '??Error!',
                'icon' => 'error',
                'message' => 'No se ha podido actualizar correctamente la contrase??a'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $graduate = $this->userRepository->getById($id);

            $person = $this->personRepository->getById($graduate->person_id);
            
            

            DB::beginTransaction();

            $this->personRepository->delete($person);

           DB::commit();
            
           
            return back()->with('alert', ['title' => '????xito!', 'message' => 'Se ha eliminado correctamente.', 'icon' => 'success']);
        } catch (\Exception $th) {
            DB::rollBack();
            return $th->getMessage();
            return back()->with('alert', ['title' => '??Error!', 'message' => 'No se ha podido eliminar correctamente.', 'icon' => 'error']);
        }

    }

    /**
     * @param StoreRequest $request
     * @param array $params
     */
    public function saveImage($request): array
    {
        $file = $request->file('image');

        $params = [];

        $fileName = time() . '_people_image.' . $file->getClientOriginalExtension();

        $this->personRepository->saveImage(File::get($file), $fileName);

        $params['image_url'] =  'storage/images/people/';
        $params['image'] = $fileName;

        return $params;

    } 
}