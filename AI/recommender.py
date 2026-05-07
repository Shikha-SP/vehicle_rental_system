import pandas as pd
import numpy as np
from knn_model import KNNModel
from preprocess import preprocess

class Recommender:
    def __init__(self, csv_path):
        self.df = pd.read_csv(csv_path)

        self.processed, self.features = preprocess(self.df)

        # convert to numpy
        X = self.processed[self.features].values

        # FEATURE WEIGHTS
        weights = np.array([
            3.0,  # price
            2.0,  # fuel_type
            2.0,  # transmission
            2.5,  # license_type
            1.5,  # top_speed
            1.5,  # fuel_capacity
            0.5   # color_group
        ])

        # apply weighting
        X = X * weights

        self.model = KNNModel()
        self.model.train(X)
        self.X = X

    def recommend(self, vehicle_id):
        index = self.df[self.df["id"] == vehicle_id].index[0]

        indices, _ = self.model.get_similar(self.X, index)

        return self.df.iloc[indices]