<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PedidosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Idempotency_key'   => 'required',
            'name'  => 'required|string|min:3|max:100|',
            'email' => 'required|string|email|max:150',
            'quantity' => 'required|integer|min:1',
            'price'     => 'required|decimal:0,2'
        ];
    }

    public function messages()
    {
        return [
            'name.required'   => "O campo name é obrigatorio.",
            'name.string'   => "O name deve ser string",
            'name.min'  => "O name deve ter no minimo 3 caracteres",
            'name.max'  => "O name deve ter no maximo 100 caracteres",
            
            'email.required' => " O campo email é obrigatorio",
            'email.email'   => "Por favor, informe um email valido",
            'email.max'     => "O email deve ter no maximo 150 caractres",
            
            'quantity.required' => 'O campo quantity é obrigatorio',
            'quantity.integer' => 'A quantity deve ser um valor inteiro',
            'quantity.min'  => 'O campo quantity deve ter no minimo 1 caractres',

            'price.required'    => 'O campo price é obrigatorio',
            'price.decimal'    => 'O campo de price deve ter de 2 a 4 casas decimais.'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'sucesso' => false,
            'message' => $validator->errors()
        ]));
    }
}
