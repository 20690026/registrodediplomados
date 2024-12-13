<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Diplomado;
use App\Models\Modulo;
use App\Models\Participante;
use App\Models\solicitud_docente;
use App\Models\solicitud_instructore;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DiplomadosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $diplomados = Diplomado::all();

        return view('diplomados.admin.diplomadosregistrados', compact('diplomados'));
    }

    public function detalles($id)
    {
        $diplomado = Diplomado::find($id);
        $modulos = Modulo::where('diplomado_id', $id)->with('instructore', 'calificacionesModulos')->orderBy('numero')->get();

        return view('diplomados.detallediplomado', compact('diplomado', 'modulos'));
    }

    public function detalles_participante($id)
    {
        $diplomado = Diplomado::find($id);
        $user = Auth::user();
        $modulos = Modulo::where('diplomado_id', $id)->with('instructore')->orderBy('numero')->get();

        return view('diplomados.participante.detallediplomado', compact('diplomado', 'modulos', 'user'));
    }

    public function detalles_instructor($id)
    {
        $diplomado = Diplomado::find($id);

        // Obtener los módulos junto con el instructor y las calificaciones
        $modulos = Modulo::where('diplomado_id', $id)
            ->with(['instructore.user.datos_generales', 'calificacionesModulos'])
            ->orderBy('numero')
            ->get();

        return view('diplomados.instructor.detallediplomado', compact('diplomado', 'modulos'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('diplomados.admin.registrodiplomado');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'objetivo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'clase' => 'required|string',
            'sede' => 'required|string|max:255',
            'responsable' => 'required|string|max:255',
            'correo_contacto' => 'required|email',
            'inicio_oferta' => 'required|date',
            'termino_oferta' => 'required|date',
            'inicio_realizacion' => 'required|date',
            'termino_realizacion' => 'required|date',
        ]);

        Diplomado::create($validatedData);

        return redirect()->route('diplomados.index')->with('success', 'Diplomado registrado con éxito');
    }

    public function edit($id)
    {
        $diplomado = Diplomado::findOrFail($id);
        return view('diplomados.admin.registrodiplomado', compact('diplomado'));
    }

    public function update(Request $request, $id)
    {
        $diplomado = Diplomado::findOrFail($id);
        $diplomado->update($request->all());

        return redirect()->route('diplomados.index')->with('success', 'Diplomado actualizado exitosamente');
    }

    public function mostrarOferta()
    {
        // Filtra los diplomados que están dentro del rango de fecha de oferta
        $diplomados = Diplomado::whereDate('inicio_oferta', '<=', now())
            ->whereDate('termino_oferta', '>=', now())
            ->get();

        $user = Auth::user();

        // Verifica si el usuario tiene una relación de instructor
        $diplomadosConSolicitudInstructor = [];
        if ($user->instructor) {
            $diplomadosConSolicitudInstructor = $user->instructor->solicitudes()->pluck('diplomado_id')->toArray();
        }

        // Verifica si el usuario tiene una relación de participante
        $diplomadosConSolicitudParticipante = [];
        if ($user->participante) {
            $diplomadosConSolicitudParticipante = $user->participante->solicitudes()->pluck('diplomado_id')->toArray();
        }

        // Pasa las variables a la vista
        return view('diplomados.oferta', compact(
            'diplomados',
            'user',
            'diplomadosConSolicitudInstructor',
            'diplomadosConSolicitudParticipante'
        ));
    }


    public function curso_docente()
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // Obtener los diplomados en curso asociados al participante autenticado
        $diplomados = solicitud_docente::where('participante_id', $user->participante->id)
            ->where('estatus', 2)
            ->whereHas('diplomado', function ($query) use ($hoy) {
                $query->where('inicio_realizacion', '<=', $hoy)
                    ->where('termino_realizacion', '>=', $hoy);
            })
            ->with('diplomado')
            ->get();

        return view('diplomados.participante.en_curso', compact('diplomados'));
    }

    public function terminado_docente()
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // Obtener los diplomados en curso asociados al participante autenticado
        $diplomados = solicitud_docente::where('participante_id', $user->participante->id)
            ->where('estatus', 2)
            ->whereHas('diplomado', function ($query) use ($hoy) {
                $query->where('termino_realizacion', '<', $hoy);
            })
            ->with('diplomado')
            ->get();

        return view('diplomados.participante.terminado', compact('diplomados'));
    }

    public function curso_instructor()
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // Obtener los diplomados en curso asociados al instructor autenticado
        $diplomados = solicitud_instructore::where('instructore_id', $user->instructor->id)
            ->where('estatus', 2)
            ->whereHas('diplomado', function ($query) use ($hoy) {
                $query->where('inicio_realizacion', '<=', $hoy)
                    ->where('termino_realizacion', '>=', $hoy);
            })
            ->with('diplomado')
            ->get();

        return view('diplomados.instructor.en_curso', compact('diplomados'));
    }

    public function terminado_instructor()
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // Obtener los diplomados en curso asociados al instructor autenticado
        $diplomados = solicitud_instructore::where('instructore_id', $user->instructor->id)
            ->where('estatus', 2)
            ->whereHas('diplomado', function ($query) use ($hoy) {
                $query->where('termino_realizacion', '<', $hoy);
            })
            ->with('diplomado')
            ->get();

        return view('diplomados.instructor.terminado', compact('diplomados'));
    }


    public function destroy($id)
    {
        $diplomado = Diplomado::findOrFail($id);
        $diplomado->delete();

        return redirect()->route('diplomados.index')->with('success', 'Diplomado eliminado correctamente.');
    }
}
