import pandas as pd
import numpy as np
from sklearn.preprocessing import MinMaxScaler

def get_color_group(color):
    if not isinstance(color, str):
        return 1

    color = color.lower()

    warm = ["red", "orange", "yellow", "maroon", "burgundy"]
    cool = ["blue", "black", "grey", "gray", "green", "navy"]
    neutral = ["white", "silver", "beige", "gold"]

    if any(c in color for c in warm):
        return 0
    elif any(c in color for c in cool):
        return 1
    elif any(c in color for c in neutral):
        return 2
    else:
        return 1


def preprocess(df):
    df = df.copy()

    df["color_group"] = df["color"].apply(get_color_group)

    df["transmission"] = df["transmission"].map({"Manual": 0, "Automatic": 1})
    df["fuel_type"] = df["fuel_type"].astype("category").cat.codes
    df["license_type"] = df["license_type"].astype("category").cat.codes

    # fill missing
    df = df.fillna(0)

    features = [
        "price_per_day",
        "fuel_type",
        "transmission",
        "license_type",
        "top_speed",
        "fuel_capacity",
        "color_group"
    ]

    scaler = MinMaxScaler()
    df[features] = scaler.fit_transform(df[features])

    return df, features