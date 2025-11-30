from flask import Flask, request, jsonify
import joblib
import numpy as np
import os
import traceback

app = Flask(__name__)

# Load all models
models_dir = r"D:\projects\se_project_backend\src\models"
models = {}

try:
    models['ndvi'] = joblib.load(os.path.join(models_dir, 'best_ndvi_model.pkl'))
    models['evi'] = joblib.load(os.path.join(models_dir, 'best_evi_model.pkl'))
    models['ndwi'] = joblib.load(os.path.join(models_dir, 'best_ndwi_model.pkl'))
    models['temp'] = joblib.load(os.path.join(models_dir, 'best_temp_model.pkl'))
    print("✅ All models loaded successfully")
except Exception as e:
    print(f"❌ Error loading models: {e}")
    traceback.print_exc()

@app.route("/predict/<metric>", methods=["POST"])
def predict(metric):
    """
    Predict for a single metric
    Expects JSON: { "features": [scaled_year, scaled_area_id, season_autumn, season_spring, season_summer, season_winter] }
    """
    if metric not in models:
        return jsonify({"error": f"Invalid metric. Available: {list(models.keys())}"}), 400
    
    try:
        data = request.json
        features = np.array(data["features"]).reshape(1, -1)
        prediction = models[metric].predict(features)
        
        return jsonify({
            "metric": metric,
            "prediction": float(prediction[0]),
            "status": "success"
        }), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 400

@app.route("/predict-batch", methods=["POST"])
def predict_batch():
    """
    Predict for all areas in one year/season for all metrics
    Expects JSON: {
        "year_scaled": float,
        "area_ids_scaled": [float, float, ...],
        "season": "autumn|spring|summer|winter"
    }
    Returns predictions for all metrics and all areas
    """
    try:
        if not models:
            return jsonify({"error": "Models not loaded"}), 500
            
        data = request.json
        
        # Validate required fields
        if not data or "year_scaled" not in data or "area_ids_scaled" not in data or "season" not in data:
            return jsonify({"error": "Missing required fields: year_scaled, area_ids_scaled, season"}), 400
        
        year_scaled = float(data["year_scaled"])
        area_ids_scaled = [float(x) for x in data["area_ids_scaled"]]
        season = data["season"].lower()
        
        print(f"📊 Batch prediction request: year_scaled={year_scaled}, num_areas={len(area_ids_scaled)}, season={season}")
        
        # Validate season
        valid_seasons = ["autumn", "spring", "summer", "winter"]
        if season not in valid_seasons:
            return jsonify({"error": f"Invalid season. Valid: {valid_seasons}"}), 400
        
        # One-hot encode season
        season_encoding = {
            "autumn": [1, 0, 0, 0],
            "spring": [0, 1, 0, 0],
            "summer": [0, 0, 1, 0],
            "winter": [0, 0, 0, 1]
        }
        season_vec = season_encoding[season]
        
        # Prepare features for all areas
        features_list = []
        for area_id_scaled in area_ids_scaled:
            feature_row = [year_scaled, area_id_scaled] + season_vec
            features_list.append(feature_row)
        
        features_array = np.array(features_list)
        print(f"✅ Feature array shape: {features_array.shape}")
        
        # Get predictions for all metrics
        predictions = {
            "year_scaled": year_scaled,
            "season": season,
            "area_predictions": []
        }
        
        for idx, area_id_scaled in enumerate(area_ids_scaled):
            area_pred = {
                "area_id_scaled": area_id_scaled,
                "predictions": {}
            }
            
            for metric, model in models.items():
                try:
                    pred_value = model.predict(features_array[idx:idx+1])
                    area_pred["predictions"][metric] = float(pred_value[0])
                except Exception as e:
                    print(f"❌ Error predicting {metric} for area {idx}: {e}")
                    return jsonify({"error": f"Error predicting {metric}: {str(e)}"}), 500
            
            predictions["area_predictions"].append(area_pred)
        
        print(f"✅ Predictions completed successfully: {len(predictions['area_predictions'])} areas")
        return jsonify(predictions), 200
    
    except Exception as e:
        print(f"❌ Batch prediction error: {e}")
        traceback.print_exc()
        return jsonify({"error": f"Batch prediction error: {str(e)}"}), 500

@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint"""
    return jsonify({
        "status": "healthy",
        "models_loaded": list(models.keys())
    }), 200

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
