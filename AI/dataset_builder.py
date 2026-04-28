import pandas as pd
import pymysql

def load_data():
    conn = pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="vehicle_rental_db"
    )

    query = "SELECT * FROM vehicles"
    df = pd.read_sql(query, conn)
    conn.close()

    return df


def build_dataset():
    df = load_data()

    # keep only useful fields
    df = df[[
        "id",
        "model",
        "color",
        "license_type",
        "transmission",
        "fuel_type",
        "price_per_day",
        "top_speed",
        "fuel_capacity"
    ]]

    df.to_csv("dataset.csv", index=False)
    print("Dataset created")

if __name__ == "__main__":
    build_dataset()