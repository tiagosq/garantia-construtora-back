<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Attachment as AttachmentModel;
use App\Trait\Attachment;
use App\Trait\Log;
use Directory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class QuestionController extends Controller
{
    use Log, Attachment;

    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('question', 'read', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            // Declare your fixed params here
            $defaultKeys = [
                'limit',
                'page',
                'business',
                'building',
                'maintenance',
            ];

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'limit' => 'sometimes|numeric|min:20|max:100',
                'page' => 'sometimes|numeric|min:1',
                'business' => 'sometimes|string|exists:businesses,id',
                'building' => 'sometimes|string|exists:buildings,id',
                'maintenance' => 'sometimes|string|exists:maintenances,id',
                // 'dbColumnName' => 'asc|desc'
                '*' => function ($attribute, $value, $fail) use ($defaultKeys) {
                    if (!in_array($attribute, $defaultKeys))
                    {
                        if (!in_array($value, ['asc', 'desc']))
                        {
                            $fail('[validation.order]');
                        }

                        if (!Schema::hasColumn('maintenances', $attribute))
                        {
                            $fail('[validation.column]');
                        }
                    }
                },
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $limit = (request()->has('limit') ? request()->limit : 20);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $business = request()->route()->parameter('business');
            $building = request()->route()->paramenter('building');
            $maintenance = request()->route()->parameter('maintenance');

            $sort = array_filter(request()->all(), function($key) use ($defaultKeys) {
                return !in_array($key, $defaultKeys);
            }, ARRAY_FILTER_USE_KEY);

            $query = Question::query();

            $query->select([
                'questions.question as question',
                'questions.answer as answer',
                'questions.status as status',
                'maintenances.name as maintenance',
                'buildings.name as building',
                'businesses.name as business',
                'questions.created_at as created_at',
                'questions.updated_at as updated_at',
            ]);

            if (!empty($sort))
            {
                foreach ($sort as $column => $direction)
                {
                    $query->orderBy($column, $direction);
                }
            }
            else
            {
                $query->orderBy('name', 'desc');
            }

            $query->leftJoin('maintenances', 'maintenances.id', '=', 'questions.maintenance');
            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');

            if(!empty($business))
            {
                $query->where('businesses.id', '=', $business);
            }

            if(!empty($building))
            {
                $query->where('buildings.id', '=', $building);
            }

            if(!empty($maintenance))
            {
                $query->where('maintenances.id', '=', $maintenance);
            }

            $questions = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing questions available']));
            $returnMessage =  response()->json(['message' => 'Showing questions available', 'data' => $questions]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function show()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('question', 'read', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:questions,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Question::query();

            $query->select([
                'questions.question as question',
                'questions.answer as answer',
                'questions.status as status',
                'maintenances.name as maintenance',
                'buildings.name as building',
                'businesses.name as business',
                'questions.created_at as created_at',
                'questions.updated_at as updated_at',
            ]);

            $query->leftJoin('maintenances', 'maintenances.id', '=', 'questions.maintenance');
            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->where('questions.id', '=', request()->id);
            $question = $query->first();

            $this->setAfter(json_encode(['message' => 'Showing question available']));
            $returnMessage =  response()->json(['message' => 'Showing question available', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function store()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('question', 'create', request()->route()->parameter('business')) ||
                request()->has('attachments') && !$this->checkUserPermission('attachment', 'create', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $errorBag = new MessageBag();
            $requestTreated = array_merge(
                request()->route()->parameters(),
                request()->all()
            );

            // Attachment validator
            if (!empty($requestTreated['attachments']))
            {
                $jsonValidator = Validator::make($requestTreated, [
                    'attachments' => 'sometimes|array',
                    'attachments.*.filename' => 'required_with:attachments|string',
                    'attachments.*.content' => 'required_with:attachments|string',
                ]);

                if ($jsonValidator->fails())
                {
                    $errorBag->merge($jsonValidator->errors());
                }
            }

            // Question validator
            $questionValidator = Validator::make($requestTreated, [
                'question' => 'required|string',
                'answer' => 'required|string',
                'status' => 'required|boolean',
                'maintenance' => 'required|string|exists:maintenances,id',
            ]);

            if ($questionValidator->fails())
            {
                $errorBag->merge($questionValidator->errors());
            }

            if ($errorBag->isNotEmpty())
            {
                $combinedValidator = Validator::make([], []);
                $combinedValidator->errors()->merge($errorBag);
                throw new ValidationException($combinedValidator);
            }

            $question = new Question();
            $question->id = Ulid::generate();
            $question->question = $requestTreated['question'];
            $question->answer = $requestTreated['answer'];
            $question->status = $requestTreated['status'];
            $question->maintenance = $requestTreated['maintenance'];
            $question->save();


            if (!empty($requestTreated['attachments']))
            {
                // Access related models to do the attachments path
                $maintenance = $question->maintenanceBelongs;
                $building = $maintenance->buildingBelongs;
                $business = $building->businessBelongs;

                $pathSplitted = [
                    $business->id,
                    $building->id,
                    $maintenance->id,
                    $question->id,
                ];

                $filesSavedOnStorage = [];


                foreach ($requestTreated['attachments'] as &$file)
                {
                    $attachmentInfo = $this->saveAttachment($file['content'], $pathSplitted, $file['filename']);

                    $attachment = new AttachmentModel();
                    $attachment->id = $attachmentInfo['id'];
                    $attachment->name = $attachmentInfo['filename'];
                    $attachment->path = $attachmentInfo['path'];
                    $attachment->type = $attachmentInfo['mimetype'];
                    $attachment->question = $question->id;
                    $attachment->user = auth()->user()->id;
                    $attachment->status = true;
                    $attachment->save();

                    $filesSavedOnStorage[] = $attachmentInfo['filename'];
                }
            }

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question created']));
            $returnMessage =  response()->json(['message' => 'Successfully question created', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('question', 'update', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            if ((request()->has('attachments_to_add') || request()->has('attachments_to_remove')) && !$this->checkUserPermission('attachment', 'update', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $errorBag = new MessageBag();
            $requestTreated = array_merge(
                request()->route()->parameters(),
                request()->all()
            );

            if (!empty($requestTreated['attachments_to_add']))
            {
                $requestTreated['attachments_to_add'] = json_decode($requestTreated['attachments_to_add'], true);

                $jsonValidator = Validator::make(request()->only('attachments_to_add'), [
                    'attachments_to_add' => 'sometimes|array',
                    'attachments_to_add.*.filename' => 'required_with:attachments_to_add|string',
                    'attachments_to_add.*.content' => 'required_with:attachments_to_add|string',
                ]);

                if ($jsonValidator->fails())
                {
                    $errorBag->merge($jsonValidator->errors());
                }
            }

            if (!empty($requestTreated['attachments_to_remove']))
            {
                $requestTreated['attachments_to_remove'] = json_decode($requestTreated['attachments_to_remove'], true);

                $jsonValidator = Validator::make(request()->only('attachments_to_remove'), [
                    'attachments_to_remove' => 'sometimes|array',
                    'attachments_to_remove.*.filename' => 'required_with:attachments_to_remove|string',
                ]);

                if ($jsonValidator->fails())
                {
                    $errorBag->merge($jsonValidator->errors());
                }
            }


            // Question validator
            $questionValidator = Validator::make($requestTreated, [
                'id' => 'required|string|exists:questions,id',
                'question' => 'sometimes|string',
                'answer' => 'sometimes|string',
                'status' => 'sometimes|boolean',
                'maintenance' => 'sometimes|string|exists:maintenances,id',
            ]);

            if ($questionValidator->fails())
            {
                $errorBag->merge($questionValidator->errors());
            }

            if ($errorBag->isNotEmpty())
            {
                $combinedValidator = Validator::make([], []);
                $combinedValidator->errors()->merge($errorBag);
                throw new ValidationException($combinedValidator);
            }

            $question = Question::find(request()->id);

            if (request()->has('question'))
            {
                $question->question = $requestTreated['question'];
            }
            if (request()->has('answer'))
            {
                $question->answer = $requestTreated['answer'];
            }
            if (request()->has('status'))
            {
                $question->status = $requestTreated['status'];
            }
            if (request()->has('maintenance'))
            {
                $question->maintenance = $requestTreated['maintenance'];
            }

            $question->save();

            if (!empty($requestTreated['attachments_to_add']))
            {
                // Access related models to do the attachments path
                $maintenance = $question->maintenanceBelongs;
                $building = $maintenance->buildingBelongs;
                $business = $building->businessBelongs;

                $pathSplitted = [
                    $business->id,
                    $building->id,
                    $maintenance->id,
                    $question->id,
                ];

                $filesSavedOnStorage = [];

                foreach ($requestTreated['attachments_to_add'] as &$file)
                {
                    $attachmentInfo = $this->saveAttachment($file['content'], $pathSplitted, $file['filename']);

                    $attachment = new AttachmentModel();
                    $attachment->name = $attachmentInfo['filename'];
                    $attachment->path = $attachmentInfo['path'];
                    $attachment->type = $attachmentInfo['mimetype'];
                    $attachment->question = $question->id;
                    $attachment->user = auth()->user()->id;
                    $attachment->status = true;
                    $attachment->save();

                    $filesSavedOnStorage[] = $attachmentInfo['filename'];
                }

                // Loop to remove files when user setted to exclude
                foreach ($requestTreated['attachments_to_remove'] as &$file)
                {
                    if ($this->deleteAttachment($pathSplitted, $file['filename']))
                    {
                        $attachment = AttachmentModel::where('name', '=', $file['filename']);
                        $attachment->delete();
                    }
                }
            }

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question updated']));
            $returnMessage =  response()->json(['message' => 'Successfully question updated', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    public function destroy()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('question', 'delete', request()->route()->parameter('business')))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $questionValidator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ), [
                'id' => 'required|string|exists:questions,id',
            ]);

            if ($questionValidator->fails())
            {
                throw new ValidationException($questionValidator);
            }

            $question = Question::find(request()->route()->parameter('id'))->first();

            $maintenance = $question->maintenanceBelongs;
            $building = $maintenance->buildingBelongs;
            $business = $building->businessBelongs;

            $pathSplitted = [
                $business->id,
                $building->id,
                $maintenance->id,
                $question->id,
            ];

            $attachments = AttachmentModel::where('question', '=', $question->id)->get();

            foreach ($attachments as $attachment)
            {
                $this->deleteAttachment($pathSplitted, $attachment->name);
                $attachment->delete();
            }

            $storageFolder = Storage::path(implode(DIRECTORY_SEPARATOR, $pathSplitted));
            if (File::exists($storageFolder))
            {
                File::deleteDirectory($storageFolder);
            }

            $question->delete();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question deleted']));
            $returnMessage =  response()->json(['message' => 'Successfully question deleted']);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }
}
