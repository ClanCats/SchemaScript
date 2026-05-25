# Relationships Test

    :path.maps src/Maps
    :namespace.maps App\Maps

## Job

    @name string

## Person

    @firstname string
    @lastname string
    @job: job 1:1 Job
    @manager: manager 1:1? Person
    @comments: comments 1:n Comment

## Comment

    @id int
    @text string
