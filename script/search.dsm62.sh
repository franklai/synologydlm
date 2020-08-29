#!/bin/sh

SED=/bin/sed
PHP=/bin/php
SQLITE3=/bin/sqlite3
PHP_EXT_DIR=/usr/lib/php/modules
PKG_PATH=/var/packages/DownloadStation/target
SZF_SEARCH_PHP=$PKG_PATH/btsearch/btsearch.php
OPEN_BASEDIR="$(pwd):/tmp:$PKG_PATH/btsearch:$PKG_PATH/hostscript:/var/packages/DownloadStation/etc/download"
SZF_DB_PATH=db_result.db
SEARCH_MODULE="dmhy"

COMMON_PARAMETER="
        -d display_errors=On
        -d extension_dir=$PHP_EXT_DIR
        -d extension=syno_compiler.so
        -d extension=curl.so
"
#         -d error_reporting=E_ERROR
#         -d error_reporting=E_ALL

SEARCH_PARAMETER="
        -d open_basedir=$OPEN_BASEDIR
"

Usage() {
        cat >&2 <<EOF
Usage:
        list [-v]
                list all search module information
        search [-p plugin] [query string]
                search for query string
EOF
}

##
# List plugin info
#
# @parameter Verbose: default is 0
#
##
List() {
        local verbose=0
        if [ "$1" = "-v" ]; then
                verbose=1
        fi

        local cmd="$PHP $COMMON_PARAMETER $SZF_SEARCH_PHP -p"
        local jsonList=$($cmd)

        escapedJsonList=$(echo "$jsonList" | $SED 's,",\\",g')

        local phpSrc="
                \$json = json_decode(\"$escapedJsonList\");
                if (\$json)  {
                        printf(\"%10s\\n\", 'name');
                        foreach (\$json as \$item) {
                                printf(\"%10s\\n\", \$item->name);
                        }
                } else {
                        printf(\"Failed to decode json list\\n\");
                        echo \$jsonList;
                }
        "

        if [ $verbose = 1 ]; then
                $PHP -r "print_r(json_decode(\"$escapedJsonList\", true));"
        else
                $PHP -r "$phpSrc"
        fi
}

Search() {
        local query="$1"
        local plugin=""
        if [ "$1" = "-p" ]; then
                echo "2: $2, 3: $3"
                plugin="$2"
                query="$3"
        fi

        if [ -z "$query" ]; then
                Usage
                return 1
        fi

        echo Query string: "$query"

        # -s: query string
        # -o: result db path
        # -q: plugin list string

        local timeout=10
        local cmd="timeout $timeout $PHP $COMMON_PARAMETER $SEARCH_PARAMETER $SZF_SEARCH_PHP -s $query -o $SZF_DB_PATH"
        if [ ! -z "$plugin" ]; then
                cmd="$cmd -q $plugin"
        fi

        echo == cmd ==
        echo $cmd
        echo == cmd ==

        if [ -f $SZF_DB_PATH ]; then
                echo found db "$SZF_DB_PATH" exists, remove it
                rm $SZF_DB_PATH
        fi

        # execute cmd
        echo "is searching..., timeout is $timeout"
        returnStr=$($cmd)
        echo resturn str: $returnStr

        # sqlite3 db_result.db ".schema"
        #
        # sqlite3 -column db_result.db "select title, seeds, dlurl from search_results"
        local SQL="SELECT title, seeds, dlurl FROM search_results ORDER BY seeds DESC LIMIT 10"

        $SQLITE3 -column $SZF_DB_PATH "$SQL"
}

case $1 in
h | help | -h | --help)
        Usage
        exit 1
        ;;
list | -l | --list)
        List "$2" "$3"
        exit 0
        ;;
search | --search)
        Search "$2" "$3" "$4"
        exit 0
        ;;
*)
        Usage
        exit 1
        ;;
esac

# vim: set expandtab ff=unix ts=4
