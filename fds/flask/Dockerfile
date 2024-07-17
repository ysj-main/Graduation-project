# Dockerfile (flask)

# Python 이미지를 기반으로 합니다.
FROM python:latest

# 작업 디렉토리 설정
WORKDIR /app

# 의존성 파일들을 컨테이너로 복사
COPY requirements.txt /app/

# 의존성 설치
RUN pip install --no-cache-dir -r requirements.txt

# Flask 애플리케이션 코드를 컨테이너로 복사
COPY . /app

# Flask 애플리케이션 환경변수 설정
ENV FLASK_APP=app.py

# 포트 5000을 외부로 노출
EXPOSE 5000

# Gunicorn 설치
RUN pip install gunicorn

# Flask 애플리케이션 실행
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "app:app"]
