<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Artist;
use App\Models\Area;
use App\Http\Controllers\Controller;

class AreasController extends Controller {

    public function index()
    {
        $areas = Area::all();
        return response()->json($areas);
    }

    public function show($id)
    {
        $area = Area::findOrFail($id);
        return response()->json($area);
    }

    public function store(Request $request)
    {
    $request->validate([
                'nombre' => 'required|string|max:255|unique:areas',
            ]);

            $area = Area::create([
                'nombre' => $request->nombre,
            ]);

            return response()->json($area, 200);
    }

    public function update(Request $request, $id)
    {
        $area = Area::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:255|unique:areas,nombre,' . $id,
        ]);

        // Actualizamos solo el campo 'nombre' para evitar problemas con 'id' u otros campos
        $area->update([
            'nombre' => $request->nombre,
        ]);

        return response()->json($area);
    }


    public function destroy($id)
    {
        $area = Area::findOrFail($id);
        $area->delete();
        return response()->json(null, 204);
    }

}