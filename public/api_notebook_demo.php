<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

require_role('Administrador TI');

$csrfToken = csrf_token();


function e(?string $valor): string
{
    return htmlspecialchars(
        $valor ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>API REST + Fetch | SIGATI</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            min-height: 100vh;
        }

        .topbar {
            background: #172033;
            color: #ffffff;
            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            font-size: 22px;
        }

        .topbar-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .usuario {
            font-size: 14px;
            color: #d1d5db;
        }

        .rol {
            padding: 6px 10px;
            border-radius: 20px;

            background: #ffffff;
            color: #172033;

            font-size: 12px;
            font-weight: bold;
        }

        .contenedor {
            max-width: 950px;
            margin: 30px auto;
            padding: 0 20px 50px;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

            flex-wrap: wrap;
        }

        .encabezado h2 {
            font-size: 26px;
            margin-bottom: 7px;
        }

        .encabezado p {
            color: #6b7280;
            font-size: 14px;
        }

        .boton-volver {
            display: inline-block;

            padding: 10px 15px;

            border-radius: 7px;

            text-decoration: none;

            background: #e5e7eb;
            color: #1f2937;

            font-size: 14px;
            font-weight: bold;
        }

        .panel {
            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.07);

            padding: 30px;

            margin-bottom: 25px;
        }

        .panel h3 {
            font-size: 20px;
            margin-bottom: 7px;
        }

        .descripcion {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .aviso {
            padding: 14px;

            margin-bottom: 25px;

            border-radius: 7px;

            background: #eff6ff;

            color: #1e40af;

            border: 1px solid #bfdbfe;

            font-size: 14px;
            line-height: 1.5;
        }

        .aviso-put {
            background: #fff7ed;
            color: #9a3412;
            border-color: #fed7aa;
        }

        .fila {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grupo {
            margin-bottom: 20px;
        }

        .grupo label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: bold;

            color: #374151;
        }

        .grupo input,
        .grupo select {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            background: #ffffff;

            color: #1f2937;

            font-size: 15px;
        }

        .grupo input:focus,
        .grupo select:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.10);
        }

        .grupo input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
        }

        .acciones {
            display: flex;
            justify-content: flex-end;

            margin-top: 10px;
        }

        .boton-principal {
            padding: 12px 18px;

            border: none;

            border-radius: 7px;

            background: #2563eb;
            color: #ffffff;

            font-size: 14px;
            font-weight: bold;

            cursor: pointer;
        }

        .boton-put {
            background: #d97706;
        }

        .boton-put:hover {
            background: #b45309;
        }

        .boton-principal:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .resultado {
            display: none;

            margin-top: 25px;

            padding: 16px;

            border-radius: 8px;

            font-size: 14px;

            line-height: 1.5;
        }

        .resultado-exito {
            display: block;

            background: #dcfce7;
            color: #166534;

            border: 1px solid #bbf7d0;
        }

        .resultado-error {
            display: block;

            background: #fee2e2;
            color: #991b1b;

            border: 1px solid #fecaca;
        }

        .json {
            margin-top: 15px;

            padding: 15px;

            border-radius: 7px;

            background: #111827;
            color: #f9fafb;

            overflow-x: auto;

            white-space: pre-wrap;
            word-break: break-word;

            font-family:
                Consolas,
                Monaco,
                monospace;

            font-size: 13px;
        }

        @media (max-width: 650px) {

            .fila {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .acciones {
                justify-content: stretch;
            }

            .boton-principal {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<header class="topbar">

    <h1>SIGATI</h1>

    <div class="topbar-info">

        <span class="usuario">

            <?= e(
                $_SESSION['nombre_completo']
                ?? $_SESSION['nombre_usuario']
                ?? 'Usuario'
            ); ?>

        </span>

        <span class="rol">

            <?= e(
                $_SESSION['rol']
                ?? 'Sin rol'
            ); ?>

        </span>

    </div>

</header>


<main class="contenedor">

    <section class="encabezado">

        <div>

            <h2>API REST + JavaScript Fetch</h2>

            <p>
                Pruebas de creación y actualización mediante la API interna de SIGATI.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="boton-volver"
        >
            Volver al dashboard
        </a>

    </section>


    <!-- POST -->

    <section class="panel">

        <h3>POST - Registrar notebook</h3>

        <p class="descripcion">
            Registra un nuevo notebook enviando datos JSON a la API REST.
        </p>

        <div class="aviso">

            JavaScript utiliza
            <strong>Fetch API</strong>
            para enviar un
            <strong>POST</strong>
            en formato
            <strong>JSON</strong>
            al endpoint
            <strong>/api/notebooks.php</strong>.
            Las operaciones de escritura están protegidas
            mediante token CSRF.

        </div>


        <form id="formNotebook">

            <div class="fila">

                <div class="grupo">

                    <label for="numero_serie">
                        Número de serie
                    </label>

                    <input
                        type="text"
                        id="numero_serie"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="grupo">

                    <label for="marca">
                        Marca
                    </label>

                    <input
                        type="text"
                        id="marca"
                        maxlength="50"
                        required
                    >

                </div>

            </div>


            <div class="fila">

                <div class="grupo">

                    <label for="modelo">
                        Modelo
                    </label>

                    <input
                        type="text"
                        id="modelo"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="grupo">

                    <label for="procesador">
                        Procesador
                    </label>

                    <input
                        type="text"
                        id="procesador"
                        maxlength="100"
                        required
                    >

                </div>

            </div>


            <div class="fila">

                <div class="grupo">

                    <label for="ram_gb">
                        RAM
                    </label>

                    <select
                        id="ram_gb"
                        required
                    >

                        <option value="">
                            Selecciona
                        </option>

                        <option value="8">8 GB</option>
                        <option value="16">16 GB</option>
                        <option value="32">32 GB</option>
                        <option value="64">64 GB</option>
                        <option value="128">128 GB</option>

                    </select>

                </div>


                <div class="grupo">

                    <label for="capacidad_disco_gb">
                        Capacidad de disco
                    </label>

                    <select
                        id="capacidad_disco_gb"
                        required
                    >

                        <option value="">
                            Selecciona
                        </option>

                        <option value="256">256 GB</option>
                        <option value="512">512 GB</option>
                        <option value="1024">1 TB</option>
                        <option value="2048">2 TB</option>

                    </select>

                </div>

            </div>


            <div class="acciones">

                <button
                    type="submit"
                    id="btnEnviar"
                    class="boton-principal"
                >
                    Registrar mediante API
                </button>

            </div>

        </form>


        <div
            id="resultado"
            class="resultado"
        ></div>

    </section>


    <!-- PUT -->

    <section class="panel">

        <h3>PUT - Actualizar notebook</h3>

        <p class="descripcion">
            Actualiza los datos técnicos del notebook mediante la API REST.
        </p>

        <div class="aviso aviso-put">

            Esta prueba utiliza
            <strong>PUT + Fetch</strong>.
            La API solo permitirá actualizar un notebook
            mientras permanezca en estado
            <strong>Ingresado</strong>.

        </div>


        <form id="formActualizar">

            <div class="fila">

                <div class="grupo">

                    <label for="put_id">
                        ID notebook
                    </label>

                    <input
                        type="number"
                        id="put_id"
                        value="5"
                        readonly
                    >

                </div>


                <div class="grupo">

                    <label for="put_serie">
                        Número de serie
                    </label>

                    <input
                        type="text"
                        id="put_serie"
                        value="API-DEMO-001"
                        readonly
                    >

                </div>

            </div>


            <div class="fila">

                <div class="grupo">

                    <label for="put_marca">
                        Marca
                    </label>

                    <input
                        type="text"
                        id="put_marca"
                        value="HP"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="grupo">

                    <label for="put_modelo">
                        Modelo
                    </label>

                    <input
                        type="text"
                        id="put_modelo"
                        value="EliteBook 840 G10"
                        maxlength="100"
                        required
                    >

                </div>

            </div>


            <div class="fila">

                <div class="grupo">

                    <label for="put_procesador">
                        Procesador
                    </label>

                    <input
                        type="text"
                        id="put_procesador"
                        value="Intel Core i5"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="grupo">

                    <label for="put_ram">
                        RAM
                    </label>

                    <select
                        id="put_ram"
                        required
                    >

                        <option value="8">8 GB</option>

                        <option
                            value="16"
                            selected
                        >
                            16 GB
                        </option>

                        <option value="32">32 GB</option>
                        <option value="64">64 GB</option>
                        <option value="128">128 GB</option>

                    </select>

                </div>

            </div>


            <div class="grupo">

                <label for="put_disco">
                    Capacidad de disco
                </label>

                <select
                    id="put_disco"
                    required
                >

                    <option value="256">
                        256 GB
                    </option>

                    <option
                        value="512"
                        selected
                    >
                        512 GB
                    </option>

                    <option value="1024">
                        1 TB
                    </option>

                    <option value="2048">
                        2 TB
                    </option>

                </select>

            </div>


            <div class="acciones">

                <button
                    type="submit"
                    id="btnActualizar"
                    class="boton-principal boton-put"
                >
                    Actualizar mediante PUT
                </button>

            </div>

        </form>


        <div
            id="resultadoPut"
            class="resultado"
        ></div>

    </section>

</main>


<script>

const csrfToken =
    <?= json_encode(
        $csrfToken,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    ); ?>;


/*
|--------------------------------------------------------------------------
| Función para mostrar texto de forma segura
|--------------------------------------------------------------------------
*/

function escapeHtml(texto) {

    const div =
        document.createElement('div');

    div.textContent = texto;

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

const formulario =
    document.getElementById('formNotebook');

const resultado =
    document.getElementById('resultado');

const boton =
    document.getElementById('btnEnviar');


formulario.addEventListener(
    'submit',
    async function (evento) {

        evento.preventDefault();

        resultado.className = 'resultado';
        resultado.innerHTML = '';

        boton.disabled = true;
        boton.textContent = 'Enviando...';


        const datos = {

            numero_serie:
                document
                    .getElementById('numero_serie')
                    .value
                    .trim(),

            marca:
                document
                    .getElementById('marca')
                    .value
                    .trim(),

            modelo:
                document
                    .getElementById('modelo')
                    .value
                    .trim(),

            procesador:
                document
                    .getElementById('procesador')
                    .value
                    .trim(),

            ram_gb:
                Number(
                    document
                        .getElementById('ram_gb')
                        .value
                ),

            capacidad_disco_gb:
                Number(
                    document
                        .getElementById('capacidad_disco_gb')
                        .value
                )
        };


        try {

            const respuesta =
                await fetch(
                    '../api/notebooks.php',
                    {
                        method: 'POST',

                        credentials: 'same-origin',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'X-CSRF-Token':
                                csrfToken
                        },

                        body:
                            JSON.stringify(datos)
                    }
                );


            const contenido =
                await respuesta.json();


            if (!respuesta.ok) {

                resultado.className =
                    'resultado resultado-error';

                resultado.innerHTML =
                    '<strong>Error HTTP '
                    + respuesta.status
                    + '</strong>'
                    + '<div class="json">'
                    + escapeHtml(
                        JSON.stringify(
                            contenido,
                            null,
                            4
                        )
                    )
                    + '</div>';

                return;
            }


            resultado.className =
                'resultado resultado-exito';

            resultado.innerHTML =
                '<strong>'
                + escapeHtml(
                    contenido.mensaje
                    ?? 'Operación realizada correctamente.'
                )
                + '</strong>'
                + '<div class="json">'
                + escapeHtml(
                    JSON.stringify(
                        contenido,
                        null,
                        4
                    )
                )
                + '</div>';


            formulario.reset();


        } catch (error) {

            resultado.className =
                'resultado resultado-error';

            resultado.textContent =
                'No fue posible comunicarse con la API de SIGATI.';

        } finally {

            boton.disabled = false;

            boton.textContent =
                'Registrar mediante API';
        }
    }
);


/*
|--------------------------------------------------------------------------
| PUT
|--------------------------------------------------------------------------
*/

const formularioPut =
    document.getElementById('formActualizar');

const resultadoPut =
    document.getElementById('resultadoPut');

const botonPut =
    document.getElementById('btnActualizar');


formularioPut.addEventListener(
    'submit',
    async function (evento) {

        evento.preventDefault();

        resultadoPut.className = 'resultado';
        resultadoPut.innerHTML = '';

        botonPut.disabled = true;
        botonPut.textContent = 'Actualizando...';


        const idNotebook =
            Number(
                document
                    .getElementById('put_id')
                    .value
            );


        const datos = {

            marca:
                document
                    .getElementById('put_marca')
                    .value
                    .trim(),

            modelo:
                document
                    .getElementById('put_modelo')
                    .value
                    .trim(),

            procesador:
                document
                    .getElementById('put_procesador')
                    .value
                    .trim(),

            ram_gb:
                Number(
                    document
                        .getElementById('put_ram')
                        .value
                ),

            capacidad_disco_gb:
                Number(
                    document
                        .getElementById('put_disco')
                        .value
                )
        };


        try {

            const respuesta =
                await fetch(
                    '../api/notebooks.php?id='
                    + encodeURIComponent(idNotebook),
                    {
                        method: 'PUT',

                        credentials: 'same-origin',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'X-CSRF-Token':
                                csrfToken
                        },

                        body:
                            JSON.stringify(datos)
                    }
                );


            const contenido =
                await respuesta.json();


            if (!respuesta.ok) {

                resultadoPut.className =
                    'resultado resultado-error';

                resultadoPut.innerHTML =
                    '<strong>Error HTTP '
                    + respuesta.status
                    + '</strong>'
                    + '<div class="json">'
                    + escapeHtml(
                        JSON.stringify(
                            contenido,
                            null,
                            4
                        )
                    )
                    + '</div>';

                return;
            }


            resultadoPut.className =
                'resultado resultado-exito';

            resultadoPut.innerHTML =
                '<strong>'
                + escapeHtml(
                    contenido.mensaje
                    ?? 'Notebook actualizado correctamente.'
                )
                + '</strong>'
                + '<div class="json">'
                + escapeHtml(
                    JSON.stringify(
                        contenido,
                        null,
                        4
                    )
                )
                + '</div>';


        } catch (error) {

            resultadoPut.className =
                'resultado resultado-error';

            resultadoPut.textContent =
                'No fue posible comunicarse con la API de SIGATI.';

        } finally {

            botonPut.disabled = false;

            botonPut.textContent =
                'Actualizar mediante PUT';
        }
    }
);

</script>

</body>

</html>