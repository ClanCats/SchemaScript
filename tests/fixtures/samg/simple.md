# Test API Models

Simple test SAMG file.

    :path.maps src/Maps
    :namespace.maps App\Maps

## User

    @id int
    @firstname string
    @lastname string
    @email string?

## Profile

    @user_id: userId int
    @display_name: displayName string
    @is_su: isAdmin bool
    @bio string?
