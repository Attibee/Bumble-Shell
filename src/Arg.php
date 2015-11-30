<?php

/* 
 * Copyright 2015 Attibee (http://attibee.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Bumble\Shell;

/**
 * Create and parse command line arguments.
 * 
 * The Arg class allows a program to parse command line arguments in the format:
 *      php script.php <options> parameter1 parameter2 ...
 * 
 * Parameters are required values that always appear at the end of the command. Since
 * parameters are required, they have no flag preceding the argument.
 * 
 * Options are optional. They have a single-character flag preceding them. An option may
 * be a switch or an input. A switch option always stands alone. It is either true or
 * false. An input option accepts a string, either as the same argument after the flag,
 * or as the next argument.
 * 
 * Example:
 * //create command line arguments for a "copy" command
 * $args = new Bumble\Shell\Args();
 * 
 * //add required parameters
 * $args->addParameters(array(
 *      'source',
 *      'destination'
 * ));
 * $args->addOptions(array(
 *      'r' => Args::SWITCH, //recursively copy
 *      'z' => Args::INPUT //zip the file to location
 * ));
 * 
 * We may now run the commands:
 *     php copy.php ./my-files/important.txt ./backups
 *     php copy.php -r ./my-files ./backups
 *     php copy.php -r -z myzip.zip ./my-files ./backups
 * 
 * If we tried to run "php copy.php -z ./my-files ./backups", the parser would throw
 * an error that the flag -z requires a parameter, since it is type Args::INPUT.
 */
class Arg {
    protected $parameters = array();
    protected $options = array();
    
    private $abstractParameters = array();
    private $abstractOptions = array();

    const OPT_SWITCH = 'SWITCH';
    const OPT_PARAM = 'PARAM';
    
    /**
     * Adds an array of option to the arguments.
     * 
     * The array must be in the format name=>type, where type is Args::SWITCH or
     * Args::PARAM.
     * 
     * @param array $options An arry of options name=>type.
     */
    public function addOptions( array $options ) {
        foreach( $options as $key=>$value ) {
            $this->addOption( $key, $value );
        }
    }
    
    /**
     * Adds an option to the argument parser.
     * 
     * @param string $opt The name of the option.
     * @param string $type Args::SWITCH or Args::PARAM
     */
    public function addOption( $opt, $type ) {
        if( $type !== self::OPT_PARAM || $type !== self::OPT_SWITCH ) {
            return;
        }
        
        $this->options[(string)$opt] = $type;
    }
    
    /**
     * Adds an array of parameters to the args parser.
     * 
     * @param array $parameters The array of paramter names.
     */
    public function addParameters( array $parameters ) {
        foreach( $parameters as $name ) {
            $this->addParameter( $name );
        }
    }
    
    /**
     * Adds a parameter to the args parser.
     *
     * @param string $parameter The parameter name.
     */
    public function addParameter( $parameter ) {
        $this->parameters[] = (string)$parameter;;
    }
    
    /**
     * Parses the parameters and throws an error if the user supplied an invalid format.
     */
    public function parse() {
        global $argv;

        $options = array();
        $parameters = array();
        $argc = count( $argv );
        $paramCount = count( $this->parameters );
        
        //get parameters
        if( $argc - 1 < $paramCount ) {
            throw new \Exception("The command requires $paramCount parameters.");
        }
        
        //last items are the params
        for( $i = $paramCount - 1; $i >= 0; $i-- ) {
            $this->abstractParameters[$this->parameters[$i]] = $argv[$argc - $i - 1];
        }
        
        //go through each arg
        for( $i = 1; $i < $argc - $paramCount; $i++ ) {
            $arg = $argv[$i];
            
            //cannot start without a switch
            if( !$this->isOption( $arg ) ) {
                throw new \Exception("Invalid argument \"$arg\".");
            }
            
            //check if it's a valid option
            $option = $arg[1];

            if( $this->isParam( $option ) ) {
                //the arg stands on its own, such as -f, not -ffile.txt
                //set next as its value, move $i ahead
                if( $this->isSolitary( $arg ) ) {
                    //last item
                    if( $i == $argc - $paramCount - 1 ) {
                        throw new \Exception("Parameter does not follow the option \"$option\".");
                    }
                    
                    $this->abstractOptions[$option] = $argv[$i+1];
                    $i++;
                } else {
                    $this->abstractOptions[$option] = substr( $arg, 2 );
                }
            } else if( $this->isSwitch( $option ) ) {
                //loop through all switches in the param
                for( $i = 1; $i < strlen( $param ) - 1; $i++ ) {
                    $s = $arg[$i];
                    
                    //not a switch
                    if( !$this->isSwitch( $arg[$i] ) ) {
                        throw new \Exception("An invalid switch \"$s\" was provided.");
                    }
                    
                    $this->abstractOptions[$s] = true;
                }
            }
        }
    }
    
    private function isSwitch( $switch ) {
        return $this->options[$switch]['type'] == self::OPT_SWITCH;
    }
   
    private function isParam( $switch ) {
        return !$this->isSwitch( $switch );
    }
    
    private function isSolitary( $arg ) {
        return strlen( $arg ) == 2;
    }
    
    private function isOption( $str ) {
        return $str[0] === '-' && array_key_exists( $str[1], $this->options );
    }
    
    public function getOption( $value ) {
        if( !array_key_exists( $value, $this->options ) ) {
            throw new \Exception( "The option \"$value\" does not exist.");
        }
        
        //options are... optional, thus we return false for ones not set
        if( array_key_exists( $value, $this->abstractOptions ) ) {
            return false;
        }
        
        return $this->abstractOptions[$value];
    }
    
    public function getParam( $value ) {
        if( !array_key_exists( $value, $this->parameters ) ) {
            throw new \Exception( "The parameter \"$value\" does not exist.");
        }
        
        return $this->abstractParameters[$value];
    }
}
