from pyspark.sql import SparkSession
from pyspark.sql.functions import count, sum, avg, col
from pathlib import Path
import csv

# ---------------------------------------------------------
# SIGATI - Componente Analítico con Apache Spark
# ---------------------------------------------------------

spark = (
    SparkSession.builder
    .master("local[*]")
    .appName("SIGATI-Analitica")
    .getOrCreate()
)

# Reducir mensajes innecesarios
spark.sparkContext.setLogLevel("ERROR")

print("=" * 60)
print("SIGATI - ANALISIS DE ACTIVOS TECNOLOGICOS CON APACHE SPARK")
print("=" * 60)

# ---------------------------------------------------------
# RUTAS
# ---------------------------------------------------------

ruta_csv = Path(
    r"C:\xampp\htdocs\sigati\analytics\notebooks_sigati.csv"
)

ruta_resultados = Path(
    r"C:\xampp\htdocs\sigati\analytics\resultados"
)

ruta_resultados.mkdir(parents=True, exist_ok=True)

# ---------------------------------------------------------
# LEER DATOS EXPORTADOS DESDE MYSQL
# ---------------------------------------------------------

df = (
    spark.read
    .option("header", True)
    .option("inferSchema", True)
    .option("encoding", "UTF-8")
    .csv(str(ruta_csv))
)

print("\n1. DATOS CARGADOS DESDE SIGATI")
print("-" * 60)

df.show(truncate=False)

# ---------------------------------------------------------
# INDICADORES GENERALES
# ---------------------------------------------------------

total_notebooks = df.count()

indicadores = df.agg(
    sum("ram_gb").alias("total_ram"),
    avg("ram_gb").alias("promedio_ram"),
    sum("capacidad_disco_gb").alias("total_almacenamiento")
).collect()[0]

total_ram = indicadores["total_ram"]
promedio_ram = indicadores["promedio_ram"]
total_almacenamiento = indicadores["total_almacenamiento"]

print("\n2. INDICADORES GENERALES")
print("-" * 60)

print(f"Total de notebooks analizados: {total_notebooks}")
print(f"Memoria RAM total: {total_ram} GB")
print(f"Promedio de memoria RAM: {promedio_ram:.2f} GB")
print(
    f"Capacidad total de almacenamiento: "
    f"{total_almacenamiento} GB"
)

# ---------------------------------------------------------
# NOTEBOOKS POR ESTADO
# ---------------------------------------------------------

print("\n3. NOTEBOOKS POR ESTADO")
print("-" * 60)

df_estado = (
    df.groupBy("estado")
    .agg(count("*").alias("cantidad"))
    .orderBy(col("cantidad").desc(), col("estado"))
)

df_estado.show(truncate=False)

# ---------------------------------------------------------
# NOTEBOOKS POR MARCA
# ---------------------------------------------------------

print("\n4. NOTEBOOKS POR MARCA")
print("-" * 60)

df_marca = (
    df.groupBy("marca")
    .agg(count("*").alias("cantidad"))
    .orderBy(col("cantidad").desc(), col("marca"))
)

df_marca.show(truncate=False)

# ---------------------------------------------------------
# DISTRIBUCION POR MEMORIA RAM
# ---------------------------------------------------------

print("\n5. DISTRIBUCION POR MEMORIA RAM")
print("-" * 60)

df_ram = (
    df.groupBy("ram_gb")
    .agg(count("*").alias("cantidad"))
    .orderBy("ram_gb")
)

df_ram.show(truncate=False)

# ---------------------------------------------------------
# DISTRIBUCION POR CAPACIDAD DE DISCO
# ---------------------------------------------------------

print("\n6. DISTRIBUCION POR CAPACIDAD DE DISCO")
print("-" * 60)

df_disco = (
    df.groupBy("capacidad_disco_gb")
    .agg(count("*").alias("cantidad"))
    .orderBy("capacidad_disco_gb")
)

df_disco.show(truncate=False)

# ---------------------------------------------------------
# FUNCION PARA GUARDAR RESULTADOS
# Python guarda los CSV para evitar dependencia de
# winutils.exe / Hadoop en Windows.
# ---------------------------------------------------------

def guardar_csv_spark(dataframe, archivo, columnas):
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
            writer.writerow([
                fila[columna]
                for columna in columnas
            ])


# ---------------------------------------------------------
# GUARDAR INDICADORES GENERALES
# ---------------------------------------------------------

archivo_indicadores = (
    ruta_resultados / "indicadores_generales.csv"
)

with open(
    archivo_indicadores,
    "w",
    newline="",
    encoding="utf-8-sig"
) as f:

    writer = csv.writer(f)

    writer.writerow([
        "indicador",
        "valor"
    ])

    writer.writerow([
        "Total de notebooks",
        total_notebooks
    ])

    writer.writerow([
        "Memoria RAM total GB",
        total_ram
    ])

    writer.writerow([
        "Promedio memoria RAM GB",
        round(promedio_ram, 2)
    ])

    writer.writerow([
        "Capacidad total almacenamiento GB",
        total_almacenamiento
    ])

# ---------------------------------------------------------
# GUARDAR RESULTADOS AGRUPADOS
# ---------------------------------------------------------

guardar_csv_spark(
    df_estado,
    ruta_resultados / "notebooks_por_estado.csv",
    ["estado", "cantidad"]
)

guardar_csv_spark(
    df_marca,
    ruta_resultados / "notebooks_por_marca.csv",
    ["marca", "cantidad"]
)

guardar_csv_spark(
    df_ram,
    ruta_resultados / "notebooks_por_ram.csv",
    ["ram_gb", "cantidad"]
)

guardar_csv_spark(
    df_disco,
    ruta_resultados / "notebooks_por_disco.csv",
    ["capacidad_disco_gb", "cantidad"]
)

# ---------------------------------------------------------
# RESULTADO FINAL
# ---------------------------------------------------------

print("\n7. ARCHIVOS ANALITICOS GENERADOS")
print("-" * 60)

print(
    str(
        ruta_resultados /
        "indicadores_generales.csv"
    )
)

print(
    str(
        ruta_resultados /
        "notebooks_por_estado.csv"
    )
)

print(
    str(
        ruta_resultados /
        "notebooks_por_marca.csv"
    )
)

print(
    str(
        ruta_resultados /
        "notebooks_por_ram.csv"
    )
)

print(
    str(
        ruta_resultados /
        "notebooks_por_disco.csv"
    )
)

print("\n" + "=" * 60)
print("PROCESAMIENTO APACHE SPARK FINALIZADO CORRECTAMENTE")
print("=" * 60)

spark.stop()