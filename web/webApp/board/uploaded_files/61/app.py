from flask import Flask, request, jsonify, request, jsonify, redirect, url_for
import pandas as pd
from catboost import CatBoostClassifier
import logging
import schedule
import time
from dotenv import load_dotenv
import os
import threading
from datetime import datetime, date

app = Flask(__name__)

# 환경 변수 로드
load_dotenv()

# 로깅 설정
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s %(levelname)s %(message)s')

# 모델 로드 (사전 학습된 모델을 "catboost_model.cbm" 파일로 저장했다고 가정)
try:
    model = CatBoostClassifier()
    model.load_model("catboost_model.cbm")
    app.logger.info('Model loaded successfully.')
except Exception as e:
    app.logger.error(f'Error loading model: {e}')

@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.json

        if not data:
            raise ValueError("No JSON data received")

        # 로그로 입력 데이터 확인
        app.logger.debug(f"Received data: {data}")

        age = data.get('age')
        gender = data.get('gender')
        category = data.get('category')
        amount = data.get('amount')
        latitude = data.get('latitude')
        longitude = data.get('longitude')

        # 예측을 위한 데이터 준비
        input_data = {
            'age': age,
            'gender': gender,
            'category': category,
            'amount': amount,
            'latitude' : latitude,
            'longitude' :  longitude
        }

        # 'U'를 -1로 변환
        if input_data['age'] == 'U':
            input_data['age'] = -1
        else:
            input_data['age'] = int(input_data['age'])

        # 데이터 프레임 생성 및 타입 변환
        df = pd.DataFrame([input_data])
        df['age'] = df['age'].astype(int)
        df['gender'] = df['gender'].astype(int)
        df['category'] = df['category'].astype(int)
        df['amount'] = df['amount'].astype(float)
        df['latitude'] = df['latitude'].astype(float)
        df['longitude'] = df['longitude'].astype(float)

        # 로그로 변환된 데이터 확인
        app.logger.debug(f"DataFrame: {df}")

        # 예측
        proba = model.predict_proba(df)
        prediction = bool(proba[:, 1] >= 0.6)

        return jsonify({'prediction': prediction})

    except KeyError as e:
        logging.error("Missing key in data: %s", str(e))
        return jsonify({'error': f"Missing key: {str(e)}"}), 400

    except ValueError as e:
        logging.error("Value error: %s", str(e))
        return jsonify({'error': str(e)}), 400

    except Exception as e:
        logging.error("Error during prediction: %s", str(e))
        return jsonify({'error': str(e)}), 500
    
@app.route('/normal_transaction')
def normal_transaction():
    return "정상 거래입니다. 감사합니다." #이 부분에 정상거래.html 넣기

@app.route('/fraud_transaction')
def fraud_transaction():
    return "사기 거래가 감지되었습니다. 주의하세요."#이 부분에 사기거래알림.html과 가족알림 넣기

# 별도의 스레드에서 스케줄러 실행
def run_scheduler():
    while True:
        schedule.run_pending()
        time.sleep(1)

scheduler_thread = threading.Thread(target=run_scheduler)
scheduler_thread.start()