import numpy as np
from sklearn.neighbors import NearestNeighbors

class KNNModel:
    def __init__(self):
        self.model = NearestNeighbors(n_neighbors=10, metric="euclidean")

    def train(self, X):
        self.model.fit(X)

    def get_similar(self, X, index):
        distances, indices = self.model.kneighbors([X[index]])
        return indices[0], distances[0]