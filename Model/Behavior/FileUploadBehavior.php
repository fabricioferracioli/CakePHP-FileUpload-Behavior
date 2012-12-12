<?php

    /**
    * Behavior for generic file upload
    * @filesource
    * @author Fabrício Ferracioli <fabricioferracioli@gmail.com>
    */

    App::uses('File', 'Utility');
    App::uses('Folder', 'Utility');

    class FileUploadBehavior extends ModelBehavior
    {
        /**
        * Default method for behaviors
        * @param object $model The model that is using the behavior
        * @param array $config An array containing options for the behavior. In this case is possible to configure de root for the files, relative to APP_PATH, in uploadRoot defaults to media. Other configuration is the path inside uploadRoot, stored in uploadPath.
        */
        public function setup(&$model, $settings)
        {
            if (!isset($this->settings[$model->alias]))
            {
                $this->settings[$model->alias] = array(
                    'uploadRoot' => 'media',
                    'uploadPath' => null
                );
            }

            $this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array)$settings);
        }

        /**
        * Method responsible for the file upload to the server
        * @param object $model The model that is using the behavior
        * @param array $file The PHP array for the uploaded file as defined by the php.net documentation
        * @param array $mime_whitelist Array with accepted file formats, defined as array(extension => mimetype)
        * @return array If upload fails, a message and status, otherwise the operation status and mimetype, extension, filename and md5 of the uploaded file.
        * @see http://br2.php.net/manual/en/reserved.variables.files.php
        */
        public function upload(&$model, $file, $mime_whitelist)
        {
            $return = array('status' => false, 'message' => 'Arquivo não enviado ao servidor ou inválido.', 'filepath' => null);
            if (!empty($file) && $file['error'] == UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']))
            {
                $mimetype = $this->getMime($file['tmp_name'], $file['name']);
                if (!empty($mime_whitelist) && in_array($mimetype, $mime_whitelist))
                {
                    if (file_exists(APP . $this->settings[$model->alias]['uploadRoot']))
                    {
                        $final = true;
                        if (!file_exists(APP . $this->settings[$model->alias]['uploadRoot'] . DS . $this->settings[$model->alias]['uploadPath']))
                        {
                            $final = new Folder(APP . $this->settings[$model->alias]['uploadRoot'] . DS . $this->settings[$model->alias]['uploadPath'], true);
                        }

                        if ($final)
                        {
                            $filename = $this->generateUniqueFilename($file['name']);
                            $filepath = $this->settings[$model->alias]['uploadRoot'] . DS . $this->settings[$model->alias]['uploadPath'] . DS . $filename;
                            if (move_uploaded_file($file['tmp_name'], APP . $filepath))
                            {
                                $uploaded = new File(APP . $filepath);
                                $return['status'] = true;
                                $return['mimetype'] = $mimetype;
                                $return['extension'] = $uploaded->ext();
                                $return['filename'] = $filename;
                                $return['md5'] = md5_file(APP . $filepath);
                            }
                            else
                            {
                                $return['message'] = 'Erro ao movimentar o arquivo enviado para o diretório final.';
                            }
                        }
                        else
                        {
                            $return['message'] = 'Erro ao criar o diretório de destino.';
                        }
                    }
                    else
                    {
                        $return['message'] = 'O diretório padrão de upload não foi definido criado: ' . $this->settings[$model->alias]['uploadRoot'];
                    }
                }
                else
                {
                    $return['message'] = 'Tipo de arquivo enviado não é permitido';
                }
            }
            return $return;
        }

        /**
        * Tries to discover the mimetype of a file. To this uses the native php finfo_file or mime_content_type if available. Otherwise use an vendor to this.
        * @param string $file The filepath in the server
        * @param string $original The original filename, used as an alternative when php native functions aren't available to define the mimetype based in the file extension
        * @return string The file mimetype
        */
        protected function getMime($file, $original = null)
        {
            $mimetype = null;
            if (function_exists('finfo_file'))
            {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimetype = finfo_file($finfo, $file);
                finfo_close($finfo);

                if (is_bool($mimetype))
                {
                    App::import('Vendor', 'FileUpload.Mimetype');
                    $mime = new mimetype();
                    $mimetype = $mime->getType($original);
                }
            }
            elseif (function_exists('mime_content_type'))
            {
                $mimetype = mime_content_type($file);
            }
            else
            {
                App::import('Vendor', 'FileUpload.Mimetype');
                $mime = new mimetype();
                $mimetype = $mime->getType($original);
            }

            if($mimetype == 'text/plain')
            {
                    $f = escapeshellarg($file);
                    $mimetype = trim(`file -bi $f`);
            }

            return $mimetype;
        }

        /**
        * Generate an unique filename, using the extension from the actual filename
        * @param string $filename The filename with extension of an existing file
        * @return mixed An unique name with extension that can be used for a file or false in case of failure
        */
        protected function generateUniqueFilename($filename = null)
        {
            if (!empty($filename))
            {
                $file = new File($filename);

                return uniqid() . '.' . $file->ext();
            }
            return false;
        }
    }

?>