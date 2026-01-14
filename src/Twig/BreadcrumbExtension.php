<?php

namespace App\Twig;

use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BreadcrumbExtension extends AbstractExtension
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('generate_breadcrumbs', [$this, 'generateBreadcrumbs']),
        ];
    }

    public function generateBreadcrumbs(string $routeName, array $routeParams = [], ?string $customLabel = null): array
    {
        $breadcrumbs = [];
        
        // Mapa de rutas a breadcrumbs según el tipo de usuario
        $routeMap = [
            // Dashboard
            'app_dashboard' => ['label' => 'Inicio', 'parent' => null],
            
            // Alumno - Exámenes
            'app_examen_iniciar' => ['label' => 'Exámenes', 'parent' => 'app_dashboard'],
            'app_examen_semanal_alumno_index' => ['label' => 'Exámenes Semanales', 'parent' => 'app_dashboard'],
            'app_examen_historial' => ['label' => 'Historial de Exámenes', 'parent' => 'app_dashboard'],
            'app_examen_historial_grupo' => ['label' => 'Historial del Grupo', 'parent' => 'app_examen_historial'],
            'app_examen_detalle' => ['label' => 'Detalle del Examen', 'parent' => 'app_examen_historial'],
            
            // Alumno - Planificación
            'app_planificacion_alumno_index' => ['label' => 'Planificación', 'parent' => 'app_dashboard'],
            'app_planificacion_alumno_dia' => ['label' => 'Planificación del Día', 'parent' => 'app_planificacion_alumno_index'],
            
            // Alumno - Contenido
            'app_ley_publico_index' => ['label' => 'Leyes', 'parent' => 'app_dashboard'],
            'app_ley_publico_show' => ['label' => 'Detalle de Ley', 'parent' => 'app_ley_publico_index'],
            'app_articulo_publico_index' => ['label' => 'Artículos', 'parent' => 'app_dashboard'],
            'app_articulo_publico_show' => ['label' => 'Detalle de Artículo', 'parent' => 'app_articulo_publico_index'],
            'app_recurso_publico_index' => ['label' => 'Temario y Recursos', 'parent' => 'app_dashboard'],
            'app_boib_index' => ['label' => 'BOIB', 'parent' => 'app_dashboard'],
            'app_boib_consultar' => ['label' => 'Consultar BOIB', 'parent' => 'app_boib_index'],
            
            // Alumno - Mensajes
            'app_mensaje_alumno_index' => ['label' => 'Mensajes', 'parent' => 'app_dashboard'],
            'app_mensaje_alumno_enviar' => ['label' => 'Enviar Mensaje', 'parent' => 'app_mensaje_alumno_index'],
            
            // Alumno - Preguntas y Artículos (reportar errores)
            'app_pregunta_publica_show' => ['label' => 'Ver Pregunta', 'parent' => 'app_dashboard'],
            'app_pregunta_reportar_error' => ['label' => 'Reportar Error', 'parent' => 'app_pregunta_publica_show'],
            'app_pregunta_mensajes' => ['label' => 'Mensajes de Pregunta', 'parent' => 'app_pregunta_publica_show'],
            'app_articulo_reportar_error' => ['label' => 'Reportar Error', 'parent' => 'app_articulo_publico_show'],
            'app_articulo_mensajes' => ['label' => 'Mensajes de Artículo', 'parent' => 'app_articulo_publico_show'],
            
            // Profesor - Exámenes Semanales
            'app_examen_semanal_index' => ['label' => 'Exámenes Semanales', 'parent' => 'app_dashboard'],
            'app_examen_semanal_new' => ['label' => 'Nuevo Examen Semanal', 'parent' => 'app_examen_semanal_index'],
            'app_examen_semanal_new_general' => ['label' => 'Nuevo Examen General', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_new_municipal' => ['label' => 'Nuevo Examen Municipal', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_new_convocatoria' => ['label' => 'Nuevo Examen Convocatoria', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_new_con_preguntas' => ['label' => 'Nuevo Examen con Preguntas', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_new_con_preguntas_convocatoria' => ['label' => 'Nuevo Examen con Preguntas', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_new_old' => ['label' => 'Nuevo Examen (Antiguo)', 'parent' => 'app_examen_semanal_new'],
            'app_examen_semanal_show' => ['label' => 'Ver Examen', 'parent' => 'app_examen_semanal_index'],
            'app_examen_semanal_edit' => ['label' => 'Editar Examen', 'parent' => 'app_examen_semanal_index'],
            'app_examen_semanal_pdf' => ['label' => 'PDF del Examen', 'parent' => 'app_examen_semanal_show'],
            'app_examen_semanal_pdf_respuestas' => ['label' => 'PDF de Respuestas', 'parent' => 'app_examen_semanal_show'],
            
            // Profesor - Exámenes de Alumnos
            'app_examen_profesor' => ['label' => 'Exámenes de Alumnos', 'parent' => 'app_dashboard'],
            
            // Profesor - Alumnos
            'app_informe_mensual_index' => ['label' => 'Informes Mensuales', 'parent' => 'app_dashboard'],
            'app_recurso_especifico_index' => ['label' => 'Recursos Específicos', 'parent' => 'app_dashboard'],
            
            // Profesor - Preguntas
            'app_pregunta_index' => ['label' => 'Preguntas', 'parent' => 'app_dashboard'],
            'app_pregunta_new' => ['label' => 'Nueva Pregunta', 'parent' => 'app_pregunta_index'],
            'app_pregunta_show' => ['label' => 'Ver Pregunta', 'parent' => 'app_pregunta_index'],
            'app_pregunta_edit' => ['label' => 'Editar Pregunta', 'parent' => 'app_pregunta_index'],
            'app_pregunta_municipal_index' => ['label' => 'Preguntas Municipales', 'parent' => 'app_dashboard'],
            'app_pregunta_municipal_new' => ['label' => 'Nueva Pregunta Municipal', 'parent' => 'app_pregunta_municipal_index'],
            'app_pregunta_municipal_show' => ['label' => 'Ver Pregunta Municipal', 'parent' => 'app_pregunta_municipal_index'],
            'app_pregunta_municipal_edit' => ['label' => 'Editar Pregunta Municipal', 'parent' => 'app_pregunta_municipal_index'],
            
            // Profesor - Temas
            'app_tema_index' => ['label' => 'Temas', 'parent' => 'app_dashboard'],
            'app_tema_new' => ['label' => 'Nuevo Tema', 'parent' => 'app_tema_index'],
            'app_tema_show' => ['label' => 'Ver Tema', 'parent' => 'app_tema_index'],
            'app_tema_edit' => ['label' => 'Editar Tema', 'parent' => 'app_tema_index'],
            'app_tema_municipal_index' => ['label' => 'Temas Municipales', 'parent' => 'app_dashboard'],
            'app_tema_municipal_new' => ['label' => 'Nuevo Tema Municipal', 'parent' => 'app_tema_municipal_index'],
            'app_tema_municipal_show' => ['label' => 'Ver Tema Municipal', 'parent' => 'app_tema_municipal_index'],
            'app_tema_municipal_edit' => ['label' => 'Editar Tema Municipal', 'parent' => 'app_tema_municipal_index'],
            
            // Profesor - Tareas
            'app_tarea_index' => ['label' => 'Tareas', 'parent' => 'app_dashboard'],
            'app_tarea_new' => ['label' => 'Nueva Tarea', 'parent' => 'app_tarea_index'],
            'app_tarea_show' => ['label' => 'Ver Tarea', 'parent' => 'app_tarea_index'],
            'app_tarea_edit' => ['label' => 'Editar Tarea', 'parent' => 'app_tarea_index'],
            'app_tarea_asignar_franja' => ['label' => 'Asignar Franja', 'parent' => 'app_tarea_show'],
            
            // Profesor - Planificación
            'app_planificacion_index' => ['label' => 'Planificación', 'parent' => 'app_dashboard'],
            'app_planificacion_clonar' => ['label' => 'Clonar Planificación', 'parent' => 'app_planificacion_index'],
            'app_planificacion_new' => ['label' => 'Nueva Planificación', 'parent' => 'app_planificacion_index'],
            'app_planificacion_show' => ['label' => 'Ver Planificación', 'parent' => 'app_planificacion_index'],
            'app_planificacion_edit' => ['label' => 'Editar Planificación', 'parent' => 'app_planificacion_index'],
            'app_planificacion_actividad_new' => ['label' => 'Nueva Actividad', 'parent' => 'app_planificacion_edit'],
            'app_planificacion_actividad_edit' => ['label' => 'Editar Actividad', 'parent' => 'app_planificacion_edit'],
            'app_planificacion_semanal_index' => ['label' => 'Planificación Semanal', 'parent' => 'app_dashboard'],
            
            // Profesor - Recursos
            'app_recurso_index' => ['label' => 'Recursos', 'parent' => 'app_dashboard'],
            'app_recurso_new' => ['label' => 'Nuevo Recurso', 'parent' => 'app_recurso_index'],
            'app_recurso_show' => ['label' => 'Ver Recurso', 'parent' => 'app_recurso_index'],
            'app_recurso_edit' => ['label' => 'Editar Recurso', 'parent' => 'app_recurso_index'],
            
            // Profesor - Leyes y Artículos
            'app_ley_index' => ['label' => 'Leyes', 'parent' => 'app_dashboard'],
            'app_ley_new' => ['label' => 'Nueva Ley', 'parent' => 'app_ley_index'],
            'app_ley_show' => ['label' => 'Ver Ley', 'parent' => 'app_ley_index'],
            'app_ley_edit' => ['label' => 'Editar Ley', 'parent' => 'app_ley_index'],
            'app_articulo_index' => ['label' => 'Artículos', 'parent' => 'app_dashboard'],
            'app_articulo_new' => ['label' => 'Nuevo Artículo', 'parent' => 'app_articulo_index'],
            'app_articulo_show' => ['label' => 'Ver Artículo', 'parent' => 'app_articulo_index'],
            'app_articulo_edit' => ['label' => 'Editar Artículo', 'parent' => 'app_articulo_index'],
            
            // Profesor - Municipios y Convocatorias
            'app_municipio_index' => ['label' => 'Municipios', 'parent' => 'app_dashboard'],
            'app_municipio_new' => ['label' => 'Nuevo Municipio', 'parent' => 'app_municipio_index'],
            'app_municipio_show' => ['label' => 'Ver Municipio', 'parent' => 'app_municipio_index'],
            'app_municipio_edit' => ['label' => 'Editar Municipio', 'parent' => 'app_municipio_index'],
            'app_convocatoria_index' => ['label' => 'Convocatorias', 'parent' => 'app_dashboard'],
            'app_convocatoria_new' => ['label' => 'Nueva Convocatoria', 'parent' => 'app_convocatoria_index'],
            'app_convocatoria_show' => ['label' => 'Ver Convocatoria', 'parent' => 'app_convocatoria_index'],
            'app_convocatoria_edit' => ['label' => 'Editar Convocatoria', 'parent' => 'app_convocatoria_index'],
            
            // Admin - Usuarios
            'app_user_index' => ['label' => 'Usuarios', 'parent' => 'app_dashboard'],
            'app_user_new' => ['label' => 'Nuevo Usuario', 'parent' => 'app_user_index'],
            'app_user_show' => ['label' => 'Ver Usuario', 'parent' => 'app_user_index'],
            'app_user_edit' => ['label' => 'Editar Usuario', 'parent' => 'app_user_index'],
            
            // Admin - Configuración
            'app_configuracion_index' => ['label' => 'Configuración', 'parent' => 'app_dashboard'],
            
            // Admin - Contacto
            'app_contacto_mensajes' => ['label' => 'Mensajes de Contacto', 'parent' => 'app_dashboard'],
            'app_contacto_ver' => ['label' => 'Ver Mensaje', 'parent' => 'app_contacto_mensajes'],
            
            // Profesor - Fechas de Pruebas
            'app_fechas_pruebas_index' => ['label' => 'Fechas de Pruebas', 'parent' => 'app_dashboard'],
            
            // Profesor - Grupos
            'app_grupo_index' => ['label' => 'Grupos', 'parent' => 'app_dashboard'],
            'app_grupo_new' => ['label' => 'Nuevo Grupo', 'parent' => 'app_grupo_index'],
            'app_grupo_show' => ['label' => 'Ver Grupo', 'parent' => 'app_grupo_index'],
            'app_grupo_edit' => ['label' => 'Editar Grupo', 'parent' => 'app_grupo_index'],
            
            // Profesor - Exámenes PDF
            'app_examen_pdf_index' => ['label' => 'Exámenes PDF', 'parent' => 'app_dashboard'],
            'app_examen_pdf_new' => ['label' => 'Nuevo Examen PDF', 'parent' => 'app_examen_pdf_index'],
            'app_examen_pdf_show' => ['label' => 'Ver Examen PDF', 'parent' => 'app_examen_pdf_index'],
            'app_examen_pdf_edit' => ['label' => 'Editar Examen PDF', 'parent' => 'app_examen_pdf_index'],
            
            // Profesor - Configuración de Exámenes
            'app_configuracion_examen_index' => ['label' => 'Configuración de Exámenes', 'parent' => 'app_dashboard'],
            'app_configuracion_examen_new' => ['label' => 'Nueva Configuración', 'parent' => 'app_configuracion_examen_index'],
            'app_configuracion_examen_show' => ['label' => 'Ver Configuración', 'parent' => 'app_configuracion_examen_index'],
            'app_configuracion_examen_edit' => ['label' => 'Editar Configuración', 'parent' => 'app_configuracion_examen_index'],
            
            // Profesor - Informes Mensuales (generar)
            'app_informe_mensual_generar' => ['label' => 'Generar Informe', 'parent' => 'app_informe_mensual_index'],
            
            // Profesor - Notificaciones
            'app_notificacion_index' => ['label' => 'Notificaciones', 'parent' => 'app_dashboard'],
            
            // Alumno - Juegos
            'app_juego_adivina_numero_articulo' => ['label' => '¿Qué Número Tiene el Artículo?', 'parent' => 'app_dashboard'],
            'app_juego_adivina_nombre_articulo' => ['label' => '¿Cómo se Llama el Artículo?', 'parent' => 'app_dashboard'],
            'app_juego_completa_fecha_ley' => ['label' => '¿Cuándo se Publicó la Ley?', 'parent' => 'app_dashboard'],
            
            // Alumno - Gamificación
            'app_gamificacion_historial' => ['label' => 'Historial de Gamificación', 'parent' => 'app_dashboard'],
            'app_gamificacion_ranking' => ['label' => 'Rankings de Gamificación', 'parent' => 'app_gamificacion_historial'],
            
            // Profesor/Admin - Gamificación
            'app_gamificacion_admin' => ['label' => 'Gamificación', 'parent' => 'app_dashboard'],
            
            // Alumno - Partidas Multijugador
            'app_partida_preguntas_index' => ['label' => 'Partidas Multijugador', 'parent' => 'app_dashboard'],
            'app_partida_preguntas_new' => ['label' => 'Nueva Partida', 'parent' => 'app_partida_preguntas_index'],
            'app_partida_preguntas_show' => ['label' => 'Ver Partida', 'parent' => 'app_partida_preguntas_index'],
            
            // Tutoriales
            'app_tutorial' => ['label' => 'Tutorial', 'parent' => 'app_dashboard'],
            'app_tutorial_profesor' => ['label' => 'Tutorial Profesor', 'parent' => 'app_dashboard'],
            'app_tutorial_admin' => ['label' => 'Tutorial Admin', 'parent' => 'app_dashboard'],
            
            // Logs (Admin)
            'app_log_index' => ['label' => 'Logs', 'parent' => 'app_dashboard'],
        ];

        // Construir breadcrumbs recursivamente
        $currentRoute = $routeName;
        $visited = [];
        
        while ($currentRoute && !isset($visited[$currentRoute])) {
            $visited[$currentRoute] = true;
            
            if (isset($routeMap[$currentRoute])) {
                $label = $customLabel && $currentRoute === $routeName ? $customLabel : $routeMap[$currentRoute]['label'];
                
                // Para rutas padre, usar solo los parámetros necesarios
                $parentParams = [];
                if ($currentRoute !== $routeName) {
                    // Para rutas padre, intentar generar sin parámetros específicos
                    // o solo con los que sean comunes
                    try {
                        $url = $this->router->generate($currentRoute);
                    } catch (\Exception $e) {
                        // Si falla, intentar con parámetros mínimos
                        try {
                            $url = $this->router->generate($currentRoute, $routeParams);
                        } catch (\Exception $e2) {
                            $url = '#';
                        }
                    }
                } else {
                    // Para la ruta actual, usar todos los parámetros
                    try {
                        $url = $this->router->generate($currentRoute, $routeParams);
                    } catch (\Exception $e) {
                        $url = '#';
                    }
                }
                
                array_unshift($breadcrumbs, [
                    'label' => $label,
                    'url' => $url,
                ]);
                
                $currentRoute = $routeMap[$currentRoute]['parent'];
            } else {
                // Si la ruta no está en el mapa, añadirla con el nombre de la ruta como label
                if ($currentRoute === $routeName) {
                    $label = $customLabel ?: ucfirst(str_replace('_', ' ', str_replace('app_', '', $currentRoute)));
                    try {
                        $url = $this->router->generate($currentRoute, $routeParams);
                    } catch (\Exception $e) {
                        $url = '#';
                    }
                    array_unshift($breadcrumbs, [
                        'label' => $label,
                        'url' => $url,
                    ]);
                }
                break;
            }
        }
        
        // Siempre añadir inicio al principio si no está ya
        if (empty($breadcrumbs) || $breadcrumbs[0]['url'] !== $this->router->generate('app_dashboard')) {
            array_unshift($breadcrumbs, [
                'label' => 'Inicio',
                'url' => $this->router->generate('app_dashboard'),
            ]);
        }
        
        return $breadcrumbs;
    }
}
