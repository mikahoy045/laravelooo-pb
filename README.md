<p align="center"><a href="http://147.93.110.185:8080/api/documentation" target="_blank"><img src="https://lvlooo.s3.ap-southeast-1.amazonaws.com/zreadme/laravelooo-removebg-preview.png" width="400" alt="Laravelooo Logo"></a></p>

## About Laravelooo

This web application based on Laravel framework have 3 features:

- Page Management.
- Media Management.
- Team Management.

This project are deployed on private VPS Server using Github actions ci/cd pipeline. The *test* would run on the server before the [staging](http://147.93.110.185:8000/api/documentation) deployment to ensure the code quality. To deployed the project on the [production](http://147.93.110.185:8080/api/documentation), you'll only need to pull request to the main branch.

## Software used for this project:
- Laravel 10
- MySQL
- AWS S3 (for media storage)
- PHP 8.3

## Database Design
<img src="https://lvlooo.s3.ap-southeast-1.amazonaws.com/zreadme/laravelooo-erd.png" alt="Laravelooo Database Design">
This Database design is optimized for scalability. It follows a clean logical structure that connects all the pieces of a CMS system smoothly. The design avoids duplicating information by using proper relationships. It's a design that's both practical for current needs and flexible enough to grow.

## How to run this project
You'll need to have AWS S3 key that have been set up in your environment variables. 

1. Clone the repository
```bash
git clone git@github.com:mikahoy045/laravelooo-pr.git
```

2. Build Docker Image
```bash
docker compose build
```

3. Run the container
```bash
docker compose up -d
```

All the dependencies will be installed automatically, and the Application would be available at:
```bash
http://localhost:8080
```

## API Documentation

You can access the Swagger API documentation by running the container and accessing the link below:
```bash
http://localhost:8080/api/documentation
```

