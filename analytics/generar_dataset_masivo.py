from pyspark.sql import SparkSession
from pyspark.sql.functions import (
    col,
    concat,
    lit,
    expr,
    when,
    pmod,
    count
)
from pathlib import Path
from datetime import datetime
import csv
import time


# =========================================================
# SIGATI - PROCESAMIENTO MASIVO CON APACHE SPARK / PYSPARK
# Proyecto de Titulacion
#
# Escenario academico de gran escala:
# - 100.000 notebooks
# - 500.000 colaboradores
# - 1.500.000 asignaciones
# - 5.000.000 movimientos
#
# Total: 7.100.000 registros
# =========================================================


# ---------------------------------------------------------
# CONFIGURACION DEL ESCENARIO
# ---------------------------------------------------------

TOTAL_NOTEBOOKS = 100_000
TOTAL_COLABORADORES = 500_000
TOTAL_ASIGNACIONES = 1_500_000
TOTAL_MOVIMIENTOS = 5_000_000

PARTICIONES = 16


# ---------------------------------------------------------
# RUTA DE RESULTADOS
# ---------------------------------------------------------

ruta_resultados = Path(
    r"C:\xampp\htdocs\sigati\analytics\resultados_bigdata"
)

ruta_resultados.mkdir(
    parents=True,
    exist_ok=True
)


# ---------------------------------------------------------
# INICIAR APACHE SPARK
# ---------------------------------------------------------

spark = (
    SparkSession.builder
    .master("local[*]")
    .appName("SIGATI-BigData")
    .config(
        "spark.sql.shuffle.partitions",
        str(PARTICIONES)
    )
    .getOrCreate()
)

spark.sparkContext.setLogLevel("ERROR")


# ---------------------------------------------------------
# FUNCION PARA GUARDAR RESULTADOS PEQUENOS EN CSV
# ---------------------------------------------------------

def guardar_resultado_csv(
    dataframe,
    archivo,
    columnas
):
    """
    Los calculos se realizan con Apache Spark.

    Solamente los resultados agregados, que contienen
    pocas filas, son transferidos a Python para guardarlos
    en archivos CSV.

    De esta forma no se intenta cargar el dataset masivo
    completo en memoria de Python.
    """

    filas = dataframe.collect()

    with open(
        archivo,
        "w",
        newline="",
        encoding="utf-8-sig"
    ) as f:

        writer = csv.writer(f)

        writer.writerow(columnas)

        for fila in filas:
            writer.writerow(
                [
                    fila[columna]
                    for columna in columnas
                ]
            )


# ---------------------------------------------------------
# ENCABEZADO
# ---------------------------------------------------------

print()
print("=" * 72)
print(
    "SIGATI - PROCESAMIENTO MASIVO "
    "CON APACHE SPARK / PYSPARK"
)
print("=" * 72)

print(
    f"Spark:                "
    f"{spark.version}"
)

print(
    f"Paralelismo disponible: "
    f"{spark.sparkContext.defaultParallelism}"
)

print(
    f"Particiones utilizadas: "
    f"{PARTICIONES}"
)

print("=" * 72)


inicio_total = time.perf_counter()


# =========================================================
# 1. DATASET DE NOTEBOOKS
# =========================================================

print()
print("1. PROCESANDO NOTEBOOKS")
print("-" * 72)

inicio = time.perf_counter()

df_notebooks = (
    spark.range(
        1,
        TOTAL_NOTEBOOKS + 1,
        numPartitions=PARTICIONES
    )
    .withColumnRenamed(
        "id",
        "id_notebook"
    )
    .withColumn(
        "numero_serie",
        concat(
            lit("SIGATI-SN-"),
            col("id_notebook")
        )
    )
    .withColumn(
        "marca",
        when(
            pmod(
                col("id_notebook"),
                lit(5)
            ) == 0,
            "Dell"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(5)
            ) == 1,
            "Lenovo"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(5)
            ) == 2,
            "HP"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(5)
            ) == 3,
            "Acer"
        )
        .otherwise("ASUS")
    )
    .withColumn(
        "modelo",
        concat(
            lit("Modelo-"),
            pmod(
                col("id_notebook"),
                lit(40)
            )
        )
    )
    .withColumn(
        "procesador",
        when(
            pmod(
                col("id_notebook"),
                lit(4)
            ) == 0,
            "Intel Core i5"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(4)
            ) == 1,
            "Intel Core i7"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(4)
            ) == 2,
            "AMD Ryzen 5"
        )
        .otherwise(
            "AMD Ryzen 7"
        )
    )
    .withColumn(
        "ram_gb",
        when(
            pmod(
                col("id_notebook"),
                lit(3)
            ) == 0,
            lit(8)
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(3)
            ) == 1,
            lit(16)
        )
        .otherwise(
            lit(32)
        )
    )
    .withColumn(
        "capacidad_disco_gb",
        when(
            pmod(
                col("id_notebook"),
                lit(3)
            ) == 0,
            lit(256)
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(3)
            ) == 1,
            lit(512)
        )
        .otherwise(
            lit(1024)
        )
    )
    .withColumn(
        "estado",
        when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 0,
            "Ingresado"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 1,
            "En preparacion"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 2,
            "Disponible"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 3,
            "Asignado"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 4,
            "TBA"
        )
        .when(
            pmod(
                col("id_notebook"),
                lit(7)
            ) == 5,
            "Desactivado"
        )
        .otherwise(
            "Decomisado"
        )
    )
)

cantidad_notebooks = (
    df_notebooks.count()
)

df_notebooks_estado = (
    df_notebooks
    .groupBy("estado")
    .agg(
        count("*").alias("cantidad")
    )
    .orderBy(
        col("cantidad").desc()
    )
)

df_notebooks_marca = (
    df_notebooks
    .groupBy("marca")
    .agg(
        count("*").alias("cantidad")
    )
    .orderBy(
        col("cantidad").desc()
    )
)

guardar_resultado_csv(
    df_notebooks_estado,
    ruta_resultados /
    "notebooks_por_estado.csv",
    [
        "estado",
        "cantidad"
    ]
)

guardar_resultado_csv(
    df_notebooks_marca,
    ruta_resultados /
    "notebooks_por_marca.csv",
    [
        "marca",
        "cantidad"
    ]
)

tiempo_notebooks = (
    time.perf_counter() - inicio
)

print(
    f"Registros procesados: "
    f"{cantidad_notebooks:,}"
)

print(
    f"Particiones:          "
    f"{df_notebooks.rdd.getNumPartitions()}"
)

print(
    f"Tiempo:               "
    f"{tiempo_notebooks:.2f} segundos"
)


# =========================================================
# 2. DATASET DE COLABORADORES
# =========================================================

print()
print("2. PROCESANDO COLABORADORES")
print("-" * 72)

inicio = time.perf_counter()

df_colaboradores = (
    spark.range(
        1,
        TOTAL_COLABORADORES + 1,
        numPartitions=PARTICIONES
    )
    .withColumnRenamed(
        "id",
        "id_colaborador"
    )
    .withColumn(
        "nombre_completo",
        concat(
            lit("Colaborador "),
            col("id_colaborador")
        )
    )
    .withColumn(
        "usuario_dominio",
        concat(
            lit("usuario"),
            col("id_colaborador")
        )
    )
    .withColumn(
        "correo_corporativo",
        concat(
            lit("usuario"),
            col("id_colaborador"),
            lit("@sigati.local")
        )
    )
    .withColumn(
        "area",
        concat(
            lit("Area-"),
            pmod(
                col("id_colaborador"),
                lit(50)
            )
        )
    )
    .withColumn(
        "tipo_colaborador",
        when(
            pmod(
                col("id_colaborador"),
                lit(3)
            ) == 0,
            "Planta"
        )
        .when(
            pmod(
                col("id_colaborador"),
                lit(3)
            ) == 1,
            "Training"
        )
        .otherwise(
            "Practicante"
        )
    )
)

cantidad_colaboradores = (
    df_colaboradores.count()
)

df_colaboradores_tipo = (
    df_colaboradores
    .groupBy(
        "tipo_colaborador"
    )
    .agg(
        count("*").alias(
            "cantidad"
        )
    )
    .orderBy(
        col("cantidad").desc()
    )
)

guardar_resultado_csv(
    df_colaboradores_tipo,
    ruta_resultados /
    "colaboradores_por_tipo.csv",
    [
        "tipo_colaborador",
        "cantidad"
    ]
)

tiempo_colaboradores = (
    time.perf_counter() - inicio
)

print(
    f"Registros procesados: "
    f"{cantidad_colaboradores:,}"
)

print(
    f"Particiones:          "
    f"{df_colaboradores.rdd.getNumPartitions()}"
)

print(
    f"Tiempo:               "
    f"{tiempo_colaboradores:.2f} segundos"
)


# =========================================================
# 3. DATASET DE ASIGNACIONES
# =========================================================

print()
print("3. PROCESANDO ASIGNACIONES")
print("-" * 72)

inicio = time.perf_counter()

df_asignaciones = (
    spark.range(
        1,
        TOTAL_ASIGNACIONES + 1,
        numPartitions=PARTICIONES
    )
    .withColumnRenamed(
        "id",
        "id_asignacion"
    )
    .withColumn(
        "id_notebook",
        (
            pmod(
                col("id_asignacion"),
                lit(TOTAL_NOTEBOOKS)
            ) + 1
        ).cast("long")
    )
    .withColumn(
        "id_colaborador",
        (
            pmod(
                col("id_asignacion") * 7,
                lit(TOTAL_COLABORADORES)
            ) + 1
        ).cast("long")
    )
    .withColumn(
        "area",
        concat(
            lit("Area-"),
            pmod(
                col("id_asignacion"),
                lit(50)
            )
        )
    )
    .withColumn(
        "piso",
        (
            pmod(
                col("id_asignacion"),
                lit(20)
            ) + 1
        ).cast("int")
    )
    .withColumn(
        "asiento",
        concat(
            lit("AS-"),
            pmod(
                col("id_asignacion"),
                lit(5000)
            )
        )
    )
    .withColumn(
        "fecha_inicio",
        expr(
            "date_add("
            "date'2018-01-01', "
            "cast("
            "pmod(id_asignacion, 3000) "
            "as int)"
            ")"
        )
    )
)

cantidad_asignaciones = (
    df_asignaciones.count()
)

df_asignaciones_piso = (
    df_asignaciones
    .groupBy("piso")
    .agg(
        count("*").alias(
            "cantidad"
        )
    )
    .orderBy("piso")
)

guardar_resultado_csv(
    df_asignaciones_piso,
    ruta_resultados /
    "asignaciones_por_piso.csv",
    [
        "piso",
        "cantidad"
    ]
)

tiempo_asignaciones = (
    time.perf_counter() - inicio
)

print(
    f"Registros procesados: "
    f"{cantidad_asignaciones:,}"
)

print(
    f"Particiones:          "
    f"{df_asignaciones.rdd.getNumPartitions()}"
)

print(
    f"Tiempo:               "
    f"{tiempo_asignaciones:.2f} segundos"
)


# =========================================================
# 4. DATASET DE MOVIMIENTOS
# =========================================================

print()
print("4. PROCESANDO MOVIMIENTOS")
print("-" * 72)

inicio = time.perf_counter()

df_movimientos = (
    spark.range(
        1,
        TOTAL_MOVIMIENTOS + 1,
        numPartitions=PARTICIONES
    )
    .withColumnRenamed(
        "id",
        "id_movimiento"
    )
    .withColumn(
        "id_notebook",
        (
            pmod(
                col("id_movimiento"),
                lit(TOTAL_NOTEBOOKS)
            ) + 1
        ).cast("long")
    )
    .withColumn(
        "id_asignacion",
        (
            pmod(
                col("id_movimiento"),
                lit(TOTAL_ASIGNACIONES)
            ) + 1
        ).cast("long")
    )
    .withColumn(
        "tipo_movimiento",
        when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 0,
            "Ingreso"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 1,
            "Preparacion"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 2,
            "Asignacion"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 3,
            "Cambio de notebook"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 4,
            "Reasignacion"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 5,
            "Cambio a TBA"
        )
        .when(
            pmod(
                col("id_movimiento"),
                lit(8)
            ) == 6,
            "Desactivacion"
        )
        .otherwise(
            "Decomiso"
        )
    )
    .withColumn(
        "fecha_movimiento",
        expr(
            "timestampadd("
            "HOUR, "
            "pmod("
            "id_movimiento, "
            "70000"
            "), "
            "timestamp"
            "'2018-01-01 00:00:00'"
            ")"
        )
    )
)

cantidad_movimientos = (
    df_movimientos.count()
)

df_movimientos_tipo = (
    df_movimientos
    .groupBy(
        "tipo_movimiento"
    )
    .agg(
        count("*").alias(
            "cantidad"
        )
    )
    .orderBy(
        col("cantidad").desc()
    )
)

guardar_resultado_csv(
    df_movimientos_tipo,
    ruta_resultados /
    "movimientos_por_tipo.csv",
    [
        "tipo_movimiento",
        "cantidad"
    ]
)

tiempo_movimientos = (
    time.perf_counter() - inicio
)

print(
    f"Registros procesados: "
    f"{cantidad_movimientos:,}"
)

print(
    f"Particiones:          "
    f"{df_movimientos.rdd.getNumPartitions()}"
)

print(
    f"Tiempo:               "
    f"{tiempo_movimientos:.2f} segundos"
)


# =========================================================
# 5. CALCULAR TOTAL GENERAL
# =========================================================

total_registros = (
    cantidad_notebooks
    + cantidad_colaboradores
    + cantidad_asignaciones
    + cantidad_movimientos
)

tiempo_total = (
    time.perf_counter() - inicio_total
)


# =========================================================
# 6. GUARDAR METRICAS DE EJECUCION
# =========================================================

archivo_metricas = (
    ruta_resultados /
    "metricas_ejecucion.csv"
)

with open(
    archivo_metricas,
    "w",
    newline="",
    encoding="utf-8-sig"
) as f:

    writer = csv.writer(f)

    writer.writerow(
        [
            "metrica",
            "valor"
        ]
    )

    writer.writerow(
        [
            "Fecha ejecucion",
            datetime.now().strftime(
                "%Y-%m-%d %H:%M:%S"
            )
        ]
    )

    writer.writerow(
        [
            "Version Spark",
            spark.version
        ]
    )

    writer.writerow(
        [
            "Particiones",
            PARTICIONES
        ]
    )

    writer.writerow(
        [
            "Notebooks",
            cantidad_notebooks
        ]
    )

    writer.writerow(
        [
            "Colaboradores",
            cantidad_colaboradores
        ]
    )

    writer.writerow(
        [
            "Asignaciones",
            cantidad_asignaciones
        ]
    )

    writer.writerow(
        [
            "Movimientos",
            cantidad_movimientos
        ]
    )

    writer.writerow(
        [
            "Total registros",
            total_registros
        ]
    )

    writer.writerow(
        [
            "Tiempo notebooks segundos",
            round(
                tiempo_notebooks,
                2
            )
        ]
    )

    writer.writerow(
        [
            "Tiempo colaboradores segundos",
            round(
                tiempo_colaboradores,
                2
            )
        ]
    )

    writer.writerow(
        [
            "Tiempo asignaciones segundos",
            round(
                tiempo_asignaciones,
                2
            )
        ]
    )

    writer.writerow(
        [
            "Tiempo movimientos segundos",
            round(
                tiempo_movimientos,
                2
            )
        ]
    )

    writer.writerow(
        [
            "Tiempo total segundos",
            round(
                tiempo_total,
                2
            )
        ]
    )


# =========================================================
# 7. RESUMEN FINAL
# =========================================================

print()
print("=" * 72)
print(
    "RESUMEN DE PROCESAMIENTO BIG DATA - SIGATI"
)
print("=" * 72)

print(
    f"Apache Spark:        "
    f"{spark.version}"
)

print(
    f"Notebooks:           "
    f"{cantidad_notebooks:,}"
)

print(
    f"Colaboradores:       "
    f"{cantidad_colaboradores:,}"
)

print(
    f"Asignaciones:        "
    f"{cantidad_asignaciones:,}"
)

print(
    f"Movimientos:         "
    f"{cantidad_movimientos:,}"
)

print("-" * 72)

print(
    f"TOTAL PROCESADO:     "
    f"{total_registros:,} REGISTROS"
)

print(
    f"Particiones Spark:   "
    f"{PARTICIONES}"
)

print(
    f"Tiempo total:        "
    f"{tiempo_total:.2f} segundos"
)

print(
    f"Resultados:          "
    f"{ruta_resultados}"
)

print("=" * 72)

print(
    "PROCESAMIENTO MASIVO SIGATI "
    "FINALIZADO CORRECTAMENTE"
)

print("=" * 72)


# ---------------------------------------------------------
# CERRAR SPARK
# ---------------------------------------------------------

spark.stop()