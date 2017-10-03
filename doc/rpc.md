RPC Protocol
============

Phpactor communicates with the editor over its own RPC protocol which
effectively allows the editor to intruct Phpactor to do things, and in turn
Phpactor can also instruct the editor to do things.

![rpc](https://user-images.githubusercontent.com/530801/30521464-39743352-9bc0-11e7-92ac-06b3228adf67.png)

Requests can be sent via. `stdin` to the Phpactor `rpc` command 

```bash
$ echo '{"actions": [ {"action": "echo", "parameters": { "message": "Hello" } }]}' | ./bin/phpactor rpc
```

Above we use the somewhat pointless `echo` action, which simply returns a
response asking the editor to echo a message. The result is sent over
`stdout`:

```javascript
{"actions":[{"action":"echo","parameters":{"message":"Hello"}}]}
```

Responses contain a list of actions which will be executed sequentially in
the editor. (An exception is the `return` action which will return a value to
the caller of the RPC command, and prevent any further processing).

Some responses will include callbacks to Phpactor. This allows Phpactor to ask
for more information as required.

Work-in-progress
----------------

This Protocol is a work-in-progress.

TODO:

- It is not necessary to return a list of actions, as the `collection` action
  already fills this role.
- Parameters should not refer to file paths in class contexts: Although
  classes map 1-1 to files now, this may not always be the case and Phpactor
  can figure out a file-path from a class-name and vice-versa (so `class`
  instead of `class_path`).

Editor Actions
--------------

### `return`

Return a value. This action should return the value immediately to the caller
without further dispatches.

- **Name**: `return`
- **Parameters**:
    - **Value**: Value to return.

### `return_choice`

The editor should render a dialog asking the user to select a choice:

```
1) Do this
2) Do that
```

It should then `return` the chosen value.

- **Name**: `return_choice`
- **Parameters**:
   - `choices`: Key to value list of choices, the key is the label for the
     choice, the value is the value to return.

### `echo`

The editor should echo (or otherwise visibly display) a message.

- **Name**: `echo`
- **Parameters**:
   - `message`: Message to display.

### `error`

The editor should show an error.

- **Name**: `error`
- **Parameters**:
   - `message`: Error message to display.

### `collection`

Collection of actions to be executed sequentially until there are no more or
until a `return` is encountered.

- **Name**: `collection`
- **Parameters**:
   - `actions`: Array of actions to dispatch.

### `open_file`

Open a file in the editor.

- **Name**: `open_file`
- **Parameters**:
   - `path`: Path to file which should be opened.
   - `offset`: Goto this offset after opening the file.

### `close_file`

Close a file.

- **Name**: `close_file`
- **Parameters**:
   - `path`: Path to file which should be closed.

### `file_references`

List of files and references. The editor should populate a navigable list of
the files and the references (in VIM this is a quick-fix list).

- **Name**: `file_references`
- **Parameters**:
   - `references`: Array containing file paths nested with references:

Example of `references` parameter:

```javascript
{
    "file_references": [
        {
            "file": "/path/to/File.php",
            "references": [
                {
                    "line_no": 1234,
                    "col": 12
                },
                {
                    "line_no": 1234,
                    "col": 12
                }
            ]
        }
    ]
}
```

### `input_callback`

This action will provide a callback to Phpactor and inputs which the end-user
will need to complete to populate the parameters of the callback.

- **Name**: `input_callback`
- **Parameters**:
    - `callback`:
        - `action`: Callback command name for Phpactor.
        - `parameters`: Array of parameters to pass.
    - `inputs`: Array of inputs

Example:

```javascript
{
    "callback": {
        "action": "hello",
        "parameters": {
            "greeting": "Hello",
            "first_name": "value1"
        }
    }
    "inputs": [
        {
            "name": "first_name",
            "type": "text",
            "parameters": {
                "default": "<enter your first name>",
            }
        }
    ]
}
```

See the VIM plugin for the supported inputs.

### `information`

Show information in a persistent and unobtrusive way (in VIM as a preview
window).

- **Name**: `information`
- **Parameters**:
    - `information`: Information to show (text)

### `replace_file_source`

Replace the source code in the current file.

- **Name**: `replace_file_source`
- **Parameters**:
    - `source`: Source code.

Phpactor Commands
-----------------

In the following references all parameters are assumed to be required unless
otherwise stated. *optional* parameters are optional, *eventually required* means the parameter is optional, but if not given Phpactor will ask for it (via. a callback).

Note that after implementing a dispatcher for standard editor actions above it
is not necessary to know the result of these commands, they will be handled by
your editor.

### `complete`

The complete RPC command returns a list of completions:

```bash
$ echo '{"actions": [ {"action": "complete", "parameters": { "source": "<?php $foo = new Exception(); $foo->", "offset": 37} }] }' | ./bin/phpactor rpc
```

Example response:

```
{                                                    
    "actions": [                          
        {                          
            "action": "return",             
            "parameters": {            
                "value": [                              
                    {                      
                        "info": "pri __clone(): void",
                        "name": "__clone",  
                        "type": "f"    
                    },                                         
                    {                         
                        "info": "pub getMessage(): string",
                        "name": "getMessage",
                        "type": "f"    
                    },                                           
                    {                              
                        "info": "pub getCode()",
                        "name": "getCode",
                        "type": "f"
                    },                                     
                ]
            }
        }
    ]
}
```

### `class_search`

```bash
$ echo '{"actions": [ {"action": "class_search", "parameters": { "short_name": "InputInterface" } }] }' | ./bin/phpactor rpc
```

### `goto_definition`

Open file and goto offset of symbol at the given offset in the given source
code:

- **Name**: `goto_definition`
- **Parameters**:
    - `source`: Source code as a string
    - `offset`: Offset of symbol (int)

```bash
$ echo '{"actions": [ {"action": "goto_definition", "parameters": { "source": "<?php $foo = new Exception(); $foo->getMessage()", "offset": 37} }] }' | ./bin/phpactor rpc
```

Will return an action to open the file containing the definition and goto the
offset.

### `copy_class`

Copy a class to a new location and update its name accordingly:

- **Name**: `copy_class`
- **Parameters**:
    - `source_path`: File containing class to copy..
    - `dest_path`: (eventually required) Destination path 


```bash
$ echo '{"actions": [ {"action": "copy_class", "parameters": { "source_path": "/path/to/class.php" } } ] }| ./bin/phpactor rpc
```

Will return an action to open the new file.

### `move_class`

Move a class and update all references to it in the project.

- **Name**: `move_class`
- **Parameters**:
    - `source_path`: File containing class to move.
    - `dest_path`: (eventually required) File to move class to.

Will eventually return an action to open the new file, and a command to forget about the
old one.

### `offset_info`

Return debug information about the symbol and the state of the frame at the
given offset.

- **Name**: `offset_info`
- **Parameters**:
    - `source`: Source code as a string
    - `offset`: Offset of symbol (int)

Returns an (information) action to show a pretty-printed JSON string
containing the debug information.

### `transform`

Perform a transformation on the class in the given file.

- **Name**: `transform`
- **Parameters**:
    - `source`: Source
    - `path`: Path for file containing class to transform (required only in order
      to reload the file in the editor later).
    - `transform`: (eventually required) Name of transformation to make.

If no transformation Phpactor will offer a choice of available
transformations, then make the transformation and ask the editor to reload the
file.

### `class_new`

Generate a new class.

- **Name**: `class_new`
- **Parameters**:
    - `current_path`: Path to current file (used as default for new path)
    - `new_path`: (eventually required) Path to new class.
    - `variant`: (eventually required) Variant to create.
    - `overwrite`: (conditionally eventually required) If file already exists
      then this should be true in order that it is overwritten.

Will return an action to open the new file.

### `class_inflect`

Generate a new class based on the current (given) class.

- **Name**: `class_new`
- **Parameters**:
    - `current_path`: Path to current file (used as default for new path)
    - `new_path`: (eventually required) Path to new class.
    - `variant`: (eventually required) Variant to create.
    - `overwrite`: (conditionally eventually required) If file already exists
      then this should be true in order that it is overwritten.

Will return an action to open the new file.

### `references`

Find references to the symbol under the cursor.

- **Name**: `references`
- **Parameters**:
    - `source`: Source code as a string
    - `offset`: Offset of symbol (int)

Will return a file-list action, containing a list of all the files in which
references can be found (and the position of all the references).

### `context_menu`

Return a menu for selecting an action to perform on the current symbol

- **Name**: `context_menu`
- **Parameters**:
    - `source`: Source code as a string
    - `offset`: Offset of symbol (int)