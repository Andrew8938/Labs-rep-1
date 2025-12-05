import random
# Вариант №4

# Одномерный массив из 10 вещественных чисел
a10 = [round(random.uniform(-5, 5), 1) for _ in range(10)]
print("Массив a10:", a10)

# Вычисляем среднее арифметическое
average = sum(a10) / len(a10)
print("Среднее арифметическое:", round(average, 2))

# Проверяем, есть ли элемент, равный среднему
if average in a10:
    print("В массиве есть элемент, равный среднему арифметическому:", average)
else:
    print("Элемент, равный среднему арифметическому, в массиве не найден.")

# Двумерный массив 4x4
A = [[random.randint(1, 20) for _ in range(4)] for _ in range(4)]

print("\nМатрица A(4x4):")
for row in A:
    print(row)

# Наибольший элемент на главной диагонали
# (элементы A[0][0], A[1][1], A[2][2], A[3][3])
main_diag = [A[i][i] for i in range(4)]
max_on_diag = max(main_diag)

print("Главная диагональ:", main_diag)
print("Максимум на главной диагонали:", max_on_diag)